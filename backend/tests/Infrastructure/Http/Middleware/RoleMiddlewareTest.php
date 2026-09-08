<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Http\Middleware;

use App\Domain\Auth\ValueObjects\AccessTokenClaims;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Domain\User\UserRole;
use App\Infrastructure\Http\HttpMethod;
use App\Infrastructure\Http\JsonResponse;
use App\Infrastructure\Http\Middleware\RoleMiddleware;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\Route;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RoleMiddlewareTest extends TestCase
{
    #[Test]
    public function deixa_passar_rota_publica_sem_checar_nada(): void
    {
        $response = new RoleMiddleware()->handle(
            $this->requestFor($this->route()),
            static fn (Request $request): Response => new JsonResponse(['ok' => true]),
        );

        $this->assertSame('{"ok":true}', $response->body());
    }

    #[Test]
    public function rejeita_sem_claims_em_rota_que_exige_role(): void
    {
        try {
            new RoleMiddleware()->handle(
                $this->requestFor($this->route(UserRole::Admin)),
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
        $claims = AccessTokenClaims::issue('user-1', 'autoschedule-web', UserRole::Customer, [], 900);

        try {
            new RoleMiddleware()->handle(
                $this->requestFor($this->route(UserRole::Admin))->withAttribute('auth', $claims),
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
        $claims = AccessTokenClaims::issue('user-1', 'autoschedule-web', UserRole::Admin, [], 900);

        $response = new RoleMiddleware()->handle(
            $this->requestFor($this->route(UserRole::Admin))->withAttribute('auth', $claims),
            static fn (Request $request): Response => new JsonResponse(['ok' => true]),
        );

        $this->assertSame('{"ok":true}', $response->body());
    }

    private function route(UserRole ...$roles): Route
    {
        return new Route(HttpMethod::Get, '/api/users', static fn (Request $request): Response => new JsonResponse([]))
            ->roles(...$roles);
    }

    private function requestFor(Route $route): Request
    {
        return new Request(method: 'GET', path: $route->path)->withAttribute('route', $route);
    }
}
