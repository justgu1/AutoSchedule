<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Http\Middleware;

use App\Infrastructure\Http\JsonResponse;
use App\Infrastructure\Http\Middleware\CorsMiddleware;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CorsMiddlewareTest extends TestCase
{
    #[Test]
    public function origin_na_allowlist_ganha_os_headers_de_cors(): void
    {
        $middleware = new CorsMiddleware(['http://localhost:5173']);

        $response = $middleware->handle(
            new Request(method: 'GET', path: '/api', headers: ['origin' => 'http://localhost:5173']),
            static fn (Request $request): Response => new JsonResponse(['ok' => true]),
        );

        $this->assertSame('http://localhost:5173', $response->headers()['Access-Control-Allow-Origin']);
        $this->assertSame('true', $response->headers()['Access-Control-Allow-Credentials']);
        $this->assertSame('Origin', $response->headers()['Vary']);
    }

    #[Test]
    public function origin_fora_da_allowlist_nao_ganha_headers_de_cors(): void
    {
        $middleware = new CorsMiddleware(['http://localhost:5173']);

        $response = $middleware->handle(
            new Request(method: 'GET', path: '/api', headers: ['origin' => 'http://evil.example']),
            static fn (Request $request): Response => new JsonResponse(['ok' => true]),
        );

        $this->assertArrayNotHasKey('Access-Control-Allow-Origin', $response->headers());
    }

    #[Test]
    public function sem_header_origin_segue_sem_cors_e_sem_chamar_next_de_forma_diferente(): void
    {
        $middleware = new CorsMiddleware(['http://localhost:5173']);

        $response = $middleware->handle(
            new Request(method: 'GET', path: '/api'),
            static fn (Request $request): Response => new JsonResponse(['ok' => true]),
        );

        $this->assertArrayNotHasKey('Access-Control-Allow-Origin', $response->headers());
        $this->assertSame(200, $response->status());
    }

    #[Test]
    public function options_responde_204_direto_sem_chamar_next(): void
    {
        $middleware = new CorsMiddleware(['http://localhost:5173']);
        $nextCalled = false;

        $response = $middleware->handle(
            new Request(method: 'OPTIONS', path: '/api', headers: ['origin' => 'http://localhost:5173']),
            static function (Request $request) use (&$nextCalled): Response {
                $nextCalled = true;

                return new JsonResponse([]);
            },
        );

        $this->assertSame(204, $response->status());
        $this->assertSame('', $response->body());
        $this->assertFalse($nextCalled);
        $this->assertSame('http://localhost:5173', $response->headers()['Access-Control-Allow-Origin']);
    }

    #[Test]
    public function options_de_origin_fora_da_allowlist_tambem_responde_204_mas_sem_headers_de_cors(): void
    {
        $middleware = new CorsMiddleware(['http://localhost:5173']);

        $response = $middleware->handle(
            new Request(method: 'OPTIONS', path: '/api', headers: ['origin' => 'http://evil.example']),
            static fn (Request $request): Response => new JsonResponse([]),
        );

        $this->assertSame(204, $response->status());
        $this->assertArrayNotHasKey('Access-Control-Allow-Origin', $response->headers());
    }
}
