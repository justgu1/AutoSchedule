<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Http\Middleware;

use App\Infrastructure\Http\JsonResponse;
use App\Infrastructure\Http\Middleware\LoggingMiddleware;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LoggingMiddlewareTest extends TestCase
{
    #[Test]
    public function chama_next_e_devolve_a_resposta_sem_alterar(): void
    {
        $logged = null;
        $middleware = new LoggingMiddleware(log: function (string $line) use (&$logged): void {
            $logged = $line;
        });

        $response = $middleware->handle(
            new Request(method: 'GET', path: '/api'),
            static fn (Request $request): Response => new JsonResponse(['ok' => true], 201),
        );

        $this->assertSame('{"ok":true}', $response->body());
        $this->assertSame(201, $response->status());
        $this->assertNotNull($logged);
    }

    #[Test]
    public function loga_metodo_path_e_status_da_requisicao(): void
    {
        $logged = null;
        $middleware = new LoggingMiddleware(log: function (string $line) use (&$logged): void {
            $logged = $line;
        });

        $middleware->handle(
            new Request(method: 'POST', path: '/api/ping'),
            static fn (Request $request): Response => new JsonResponse([], 404),
        );

        $this->assertStringContainsString('POST', $logged);
        $this->assertStringContainsString('/api/ping', $logged);
        $this->assertStringContainsString('404', $logged);
    }
}
