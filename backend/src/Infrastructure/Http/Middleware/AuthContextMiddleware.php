<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Middleware;

use App\Domain\Auth\ValueObjects\AccessTokenClaims;
use App\Infrastructure\Http\Middleware;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Persistence\DatabaseConnection;

/**
 * As três marcas de contexto do RLS são compostas, não alternativas: um seller autenticado numa leitura
 * pública precisa da flag pública E da própria identidade, senão o RLS esconde a linha e ele toma 404.
 */
final readonly class AuthContextMiddleware implements Middleware
{
    public function __construct(private DatabaseConnection $connection)
    {
    }

    public function handle(Request $request, \Closure $next): Response
    {
        // O rate limit já contou a tentativa, então agora dá pra recusar o token inválido.
        $failure = $request->attribute('auth_error');

        if ($failure instanceof \Throwable) {
            throw $failure;
        }

        $claims = $request->attribute('auth');
        $authenticated = $claims instanceof AccessTokenClaims;
        $route = $request->route();

        $isServiceContext = !$authenticated && ($route?->needsServiceContext() ?? false);
        $isPublicRead = $route?->allowsPublicRead() ?? false;

        if (!$authenticated && !$isServiceContext && !$isPublicRead) {
            return $next($request);
        }

        return $this->runInTransaction($request, $next, static function (\PDO $pdo) use ($claims, $isServiceContext, $isPublicRead): void {
            if ($claims instanceof AccessTokenClaims) {
                $pdo->exec('SET LOCAL app.current_user_id = ' . $pdo->quote($claims->subject));
                $pdo->exec('SET LOCAL app.current_user_role = ' . $pdo->quote($claims->role?->value ?? ''));
            }

            if ($isServiceContext) {
                $pdo->exec("SET LOCAL app.is_service_context = 'true'");
            }

            if ($isPublicRead) {
                $pdo->exec("SET LOCAL app.is_public_read = 'true'");
            }
        });
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
