<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Middleware;

use App\Domain\Auth\Ports\TokenIssuer;
use App\Domain\Ports\DatabaseConnection;
use App\Infrastructure\Http\Middleware;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\Router;

/**
 * Decodifica o Bearer se presente e anexa as claims ao Request. Autenticado,
 * abre transação + SET LOCAL pro RLS (current_user_id/role) antes de seguir
 * -- automático em toda query, ninguém precisa lembrar de aplicar.
 *
 * Sem Bearer, a rota pode estar marcada como serviceContext (ex: login busca
 * usuário por email antes de existir qualquer autenticação) -- nesse caso
 * abre transação com um contexto de serviço mais restrito (só enxerga o
 * necessário pra autenticação em si). Nenhum dos dois casos: segue direto,
 * sem transação (rota pública comum, ou o RoleMiddleware barra depois).
 */
final class AuthContextMiddleware implements Middleware
{
    public function __construct(
        private readonly TokenIssuer $tokens,
        private readonly DatabaseConnection $connection,
        private readonly Router $router,
    ) {
    }

    public function handle(Request $request, \Closure $next): Response
    {
        $header = $request->header('authorization');

        if ($header !== null && str_starts_with($header, 'Bearer ')) {
            $claims = $this->tokens->decodeAccessToken(substr($header, 7));
            $request = $request->withAttribute('auth', $claims);

            return $this->runInTransaction($request, $next, static function (\PDO $pdo) use ($claims): void {
                $pdo->exec('SET LOCAL app.current_user_id = ' . $pdo->quote($claims->subject));
                $pdo->exec('SET LOCAL app.current_user_role = ' . $pdo->quote($claims->role?->value ?? ''));
            });
        }

        if ($this->router->isServiceContext($request->method(), $request->path())) {
            return $this->runInTransaction($request, $next, static function (\PDO $pdo): void {
                $pdo->exec("SET LOCAL app.is_service_context = 'true'");
            });
        }

        return $next($request);
    }

    private function runInTransaction(Request $request, \Closure $next, \Closure $setContext): Response
    {
        $pdo = $this->connection->pdo();
        $pdo->beginTransaction();

        try {
            $setContext($pdo);
            $response = $next($request);
            $pdo->commit();

            return $response;
        } catch (\Throwable $exception) {
            $pdo->rollBack();

            throw $exception;
        }
    }
}
