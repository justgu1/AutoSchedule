<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Middleware;

use App\Domain\Auth\Ports\TokenIssuer;
use App\Infrastructure\Database\DatabaseConnection;
use App\Infrastructure\Http\Middleware;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\Router;

/**
 * As três marcas de contexto do RLS são compostas, não alternativas: um seller autenticado numa leitura
 * pública precisa da flag pública E da própria identidade, senão o RLS esconde a linha e ele toma 404.
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
        $claims = null;

        if ($token !== null) {
            $claims = $this->tokens->decodeAccessToken($token);
            $request = $request->withAttribute('auth', $claims);
        }

        $isServiceContext = !$claims instanceof \App\Domain\Auth\ValueObjects\AccessTokenClaims && $this->router->isServiceContext($request->method(), $request->path());
        $isPublicRead = $this->router->isPublicRead($request->method(), $request->path());

        if (!$claims instanceof \App\Domain\Auth\ValueObjects\AccessTokenClaims && !$isServiceContext && !$isPublicRead) {
            return $next($request);
        }

        return $this->runInTransaction($request, $next, static function (\PDO $pdo) use ($claims, $isServiceContext, $isPublicRead): void {
            if ($claims instanceof \App\Domain\Auth\ValueObjects\AccessTokenClaims) {
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
