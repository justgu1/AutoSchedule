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
 * Decodifica o access token (header `Authorization: Bearer` ou cookie
 * `access_token` -- o que vier primeiro) e anexa as claims ao Request.
 * Autenticado, abre transação + SET LOCAL pro RLS (current_user_id/role)
 * antes de seguir -- automático em toda query, ninguém precisa lembrar de
 * aplicar.
 *
 * Sem token nenhum, a rota pode estar marcada como serviceContext (ex: login
 * busca usuário por email antes de existir qualquer autenticação) -- nesse
 * caso abre transação com um contexto de serviço mais restrito (só enxerga o
 * necessário pra autenticação em si). Nenhum dos dois casos: segue direto,
 * sem transação (rota pública comum, ou o RoleMiddleware barra depois).
 */
final readonly class AuthContextMiddleware implements Middleware
{
    public function __construct(
        private TokenIssuer $tokens,
        private DatabaseConnection $connection,
        private Router $router,
    ) {
    }

    public function handle(Request $request, \Closure $next): Response
    {
        $token = $this->extractToken($request);

        if ($token !== null) {
            $claims = $this->tokens->decodeAccessToken($token);
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

    private function extractToken(Request $request): ?string
    {
        $header = $request->header('authorization');

        if ($header !== null && str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        return $request->cookie('access_token');
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
