<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Http\Middleware;

use App\Domain\Auth\ValueObjects\AccessTokenClaims;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Domain\Users\UserRole;
use App\Infrastructure\Http\JsonResponse;
use App\Infrastructure\Http\Middleware\RoleMiddleware;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\Router;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RoleMiddlewareTest extends TestCase
{
    #[Test]
    public function deixa_passar_rota_publica_sem_checar_nada(): void
    {
        $router = new Router();
        $router->get('/api/oauth/token', static fn (): Response => new JsonResponse([]));
        $middleware = new RoleMiddleware($router);

        $response = $middleware->handle(
            new Request(method: 'GET', path: '/api/oauth/token'),
            static fn (Request $request): Response => new JsonResponse(['ok' => true]),
        );

        $this->assertSame('{"ok":true}', $response->body());
    }

    #[Test]
    public function rejeita_sem_claims_em_rota_que_exige_role(): void
    {
        $router = new Router();
        $router->get('/api/users', static fn (): Response => new JsonResponse([]), roles: ['admin']);
        $middleware = new RoleMiddleware($router);

        try {
            $middleware->handle(
                new Request(method: 'GET', path: '/api/users'),
                static fn (Request $request): Response => new JsonResponse([]),
            );
            $this->fail('Expected a DomainException to be thrown.');
        } catch (DomainException $exception) {
            $this->assertSame(DomainErrorType::Unauthorized, $exception->type());
        }
    }

    #[Test]
    public function rejeita_role_fora_da_lista_permitida(): void
    {
        $router = new Router();
        $router->get('/api/users', static fn (): Response => new JsonResponse([]), roles: ['admin']);
        $middleware = new RoleMiddleware($router);
        $claims = AccessTokenClaims::issue('user-1', 'autoschedule-web', UserRole::Customer, [], 900);

        try {
            $middleware->handle(
                new Request(method: 'GET', path: '/api/users')->withAttribute('auth', $claims),
                static fn (Request $request): Response => new JsonResponse([]),
            );
            $this->fail('Expected a DomainException to be thrown.');
        } catch (DomainException $exception) {
            $this->assertSame(DomainErrorType::Forbidden, $exception->type());
        }
    }

    #[Test]
    public function permite_role_presente_na_lista_da_rota(): void
    {
        $router = new Router();
        $router->get('/api/users', static fn (): Response => new JsonResponse([]), roles: ['admin']);
        $middleware = new RoleMiddleware($router);
        $claims = AccessTokenClaims::issue('user-1', 'autoschedule-web', UserRole::Admin, [], 900);

        $response = $middleware->handle(
            new Request(method: 'GET', path: '/api/users')->withAttribute('auth', $claims),
            static fn (Request $request): Response => new JsonResponse(['ok' => true]),
        );

        $this->assertSame('{"ok":true}', $response->body());
    }
}
