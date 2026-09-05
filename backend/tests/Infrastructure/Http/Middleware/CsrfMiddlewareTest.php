<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Http\Middleware;

use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Infrastructure\Http\JsonResponse;
use App\Infrastructure\Http\Middleware\CsrfMiddleware;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CsrfMiddlewareTest extends TestCase
{
    #[Test]
    public function get_passa_direto_e_emite_xsrf_token_quando_ainda_nao_existe(): void
    {
        $middleware = new CsrfMiddleware();

        $response = $middleware->handle(
            new Request(method: 'GET', path: '/api'),
            static fn (Request $request): Response => new JsonResponse(['ok' => true]),
        );

        $this->assertArrayHasKey('XSRF-TOKEN', $response->cookies());
        $this->assertFalse($response->cookies()['XSRF-TOKEN']['httpOnly']);
    }

    #[Test]
    public function get_nao_reemite_xsrf_token_quando_ja_existe(): void
    {
        $middleware = new CsrfMiddleware();

        $response = $middleware->handle(
            new Request(method: 'GET', path: '/api', cookies: ['XSRF-TOKEN' => 'ja-existe']),
            static fn (Request $request): Response => new JsonResponse(['ok' => true]),
        );

        $this->assertArrayNotHasKey('XSRF-TOKEN', $response->cookies());
    }

    #[Test]
    public function mutacao_sem_cookie_de_sessao_passa_direto_sem_exigir_csrf(): void
    {
        // POST /oauth/token (login) -- ainda não existe access_token cookie
        // nenhum, não tem sessão ambiente pra forjar.
        $middleware = new CsrfMiddleware();
        $nextCalled = false;

        $middleware->handle(
            new Request(method: 'POST', path: '/api/oauth/token'),
            static function (Request $request) use (&$nextCalled): Response {
                $nextCalled = true;

                return new JsonResponse([]);
            },
        );

        $this->assertTrue($nextCalled);
    }

    #[Test]
    public function mutacao_com_bearer_explicito_pula_a_checagem_de_csrf(): void
    {
        $middleware = new CsrfMiddleware();
        $nextCalled = false;

        $middleware->handle(
            new Request(
                method: 'POST',
                path: '/api/users',
                headers: ['authorization' => 'Bearer some-token'],
                cookies: ['access_token' => 'cookie-token'],
            ),
            static function (Request $request) use (&$nextCalled): Response {
                $nextCalled = true;

                return new JsonResponse([]);
            },
        );

        $this->assertTrue($nextCalled);
    }

    #[Test]
    public function mutacao_autenticada_por_cookie_sem_header_csrf_e_bloqueada(): void
    {
        $middleware = new CsrfMiddleware();

        $this->expectException(DomainException::class);

        try {
            $middleware->handle(
                new Request(
                    method: 'POST',
                    path: '/api/users',
                    cookies: ['access_token' => 'cookie-token', 'XSRF-TOKEN' => 'segredo'],
                ),
                static fn (Request $request): Response => new JsonResponse([]),
            );
        } catch (DomainException $exception) {
            $this->assertSame(DomainErrorType::Forbidden, $exception->type());

            throw $exception;
        }
    }

    #[Test]
    public function mutacao_autenticada_por_cookie_com_header_csrf_divergente_e_bloqueada(): void
    {
        $middleware = new CsrfMiddleware();

        $this->expectException(DomainException::class);

        $middleware->handle(
            new Request(
                method: 'POST',
                path: '/api/users',
                headers: ['x-csrf-token' => 'errado'],
                cookies: ['access_token' => 'cookie-token', 'XSRF-TOKEN' => 'segredo'],
            ),
            static fn (Request $request): Response => new JsonResponse([]),
        );
    }

    #[Test]
    public function mutacao_autenticada_por_cookie_com_header_csrf_correto_passa(): void
    {
        $middleware = new CsrfMiddleware();
        $nextCalled = false;

        $middleware->handle(
            new Request(
                method: 'POST',
                path: '/api/users',
                headers: ['x-csrf-token' => 'segredo'],
                cookies: ['access_token' => 'cookie-token', 'XSRF-TOKEN' => 'segredo'],
            ),
            static function (Request $request) use (&$nextCalled): Response {
                $nextCalled = true;

                return new JsonResponse([]);
            },
        );

        $this->assertTrue($nextCalled);
    }
}
