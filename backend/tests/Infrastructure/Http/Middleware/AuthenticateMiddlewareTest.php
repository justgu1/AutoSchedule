<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Http\Middleware;

use App\Domain\Auth\ValueObjects\AccessTokenClaims;
use App\Domain\User\UserRole;
use App\Infrastructure\Http\JsonResponse;
use App\Infrastructure\Http\Middleware\AuthenticateMiddleware;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeTokenIssuer;

final class AuthenticateMiddlewareTest extends TestCase
{
    #[Test]
    public function sem_token_nao_anexa_nada(): void
    {
        $seen = 'nao-visitado';

        new AuthenticateMiddleware(new FakeTokenIssuer())->handle(
            new Request(method: 'GET', path: '/api/me'),
            function (Request $request) use (&$seen): Response {
                $seen = $request->attribute('auth');

                return new JsonResponse([]);
            },
        );

        $this->assertNull($seen);
    }

    #[Test]
    public function anexa_as_claims_do_bearer(): void
    {
        $claims = $this->claims();
        $seen = null;

        new AuthenticateMiddleware(new FakeTokenIssuer(['valid' => $claims]))->handle(
            new Request(method: 'GET', path: '/api/me', headers: ['authorization' => 'Bearer valid']),
            function (Request $request) use (&$seen): Response {
                $seen = $request->attribute('auth');

                return new JsonResponse([]);
            },
        );

        $this->assertSame($claims, $seen);
    }

    #[Test]
    public function sem_header_cai_pro_cookie(): void
    {
        $claims = $this->claims();
        $seen = null;

        new AuthenticateMiddleware(new FakeTokenIssuer(['valid' => $claims]))->handle(
            new Request(method: 'GET', path: '/api/me', cookies: ['access_token' => 'valid']),
            function (Request $request) use (&$seen): Response {
                $seen = $request->attribute('auth');

                return new JsonResponse([]);
            },
        );

        $this->assertSame($claims, $seen);
    }

    #[Test]
    public function header_tem_prioridade_sobre_o_cookie(): void
    {
        $claims = $this->claims();
        $seen = null;

        new AuthenticateMiddleware(new FakeTokenIssuer(['do-header' => $claims]))->handle(
            new Request(
                method: 'GET',
                path: '/api/me',
                headers: ['authorization' => 'Bearer do-header'],
                cookies: ['access_token' => 'do-cookie-que-nao-existe'],
            ),
            function (Request $request) use (&$seen): Response {
                $seen = $request->attribute('auth');

                return new JsonResponse([]);
            },
        );

        $this->assertSame($claims, $seen);
    }

    /** Token inválido não pode pular a cota: quem recusa é o AuthContextMiddleware, depois do rate limit. */
    #[Test]
    public function token_invalido_guarda_a_falha_e_segue_o_pipeline(): void
    {
        $nextCalled = false;
        $seen = null;

        new AuthenticateMiddleware(new FakeTokenIssuer())->handle(
            new Request(method: 'GET', path: '/api/me', headers: ['authorization' => 'Bearer lixo']),
            function (Request $request) use (&$nextCalled, &$seen): Response {
                $nextCalled = true;
                $seen = $request->attribute('auth_error');

                return new JsonResponse([]);
            },
        );

        $this->assertTrue($nextCalled);
        $this->assertInstanceOf(\Throwable::class, $seen);
    }

    private function claims(): AccessTokenClaims
    {
        return AccessTokenClaims::issue('11111111-1111-4111-8111-111111111111', 'autoschedule-web', UserRole::Customer, [], 900);
    }
}
