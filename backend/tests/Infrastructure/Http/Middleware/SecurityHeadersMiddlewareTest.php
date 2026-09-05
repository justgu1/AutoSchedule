<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Http\Middleware;

use App\Infrastructure\Http\JsonResponse;
use App\Infrastructure\Http\Middleware\SecurityHeadersMiddleware;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SecurityHeadersMiddlewareTest extends TestCase
{
    #[Test]
    public function decora_a_resposta_com_os_headers_de_seguranca(): void
    {
        $middleware = new SecurityHeadersMiddleware();

        $response = $middleware->handle(
            new Request(method: 'GET', path: '/api'),
            static fn (Request $request): Response => new JsonResponse(['ok' => true]),
        );

        $this->assertSame('nosniff', $response->headers()['X-Content-Type-Options']);
        $this->assertSame('no-referrer', $response->headers()['Referrer-Policy']);
        $this->assertSame('DENY', $response->headers()['X-Frame-Options']);
        $this->assertSame("default-src 'none'", $response->headers()['Content-Security-Policy']);
        $this->assertSame('no-store', $response->headers()['Cache-Control']);
        $this->assertArrayNotHasKey('Strict-Transport-Security', $response->headers());
    }

    #[Test]
    public function inclui_hsts_so_quando_habilitado(): void
    {
        $middleware = new SecurityHeadersMiddleware(hstsEnabled: true);

        $response = $middleware->handle(
            new Request(method: 'GET', path: '/api'),
            static fn (Request $request): Response => new JsonResponse([]),
        );

        $this->assertSame('max-age=31536000; includeSubDomains', $response->headers()['Strict-Transport-Security']);
    }

    #[Test]
    public function decora_ate_resposta_de_erro(): void
    {
        $middleware = new SecurityHeadersMiddleware();

        $response = $middleware->handle(
            new Request(method: 'GET', path: '/api'),
            static fn (Request $request): Response => Response::error('Not Found', 404),
        );

        $this->assertSame('nosniff', $response->headers()['X-Content-Type-Options']);
    }
}
