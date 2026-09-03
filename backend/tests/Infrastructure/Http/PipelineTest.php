<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Http;

use App\Infrastructure\Http\JsonResponse;
use App\Infrastructure\Http\Middleware;
use App\Infrastructure\Http\Pipeline;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PipelineTest extends TestCase
{
    #[Test]
    public function sem_middleware_so_chama_o_destino(): void
    {
        $pipeline = new Pipeline([]);

        $response = $pipeline->process(
            new Request(method: 'GET', path: '/api'),
            static fn (Request $request): Response => new JsonResponse(['ok' => true]),
        );

        $this->assertSame('{"ok":true}', $response->body());
    }

    #[Test]
    public function roda_os_middlewares_na_ordem_de_registro_por_fora_pra_dentro(): void
    {
        $order = new \ArrayObject();

        $tracking = static function (string $name) use ($order): Middleware {
            return new class($name, $order) implements Middleware {
                public function __construct(
                    private readonly string $name,
                    private readonly \ArrayObject $order,
                ) {
                }

                public function handle(Request $request, \Closure $next): Response
                {
                    $this->order[] = "{$this->name}:before";
                    $response = $next($request);
                    $this->order[] = "{$this->name}:after";

                    return $response;
                }
            };
        };

        $pipeline = new Pipeline([$tracking('A'), $tracking('B')]);

        $pipeline->process(
            new Request(method: 'GET', path: '/api'),
            static function (Request $request) use ($order): Response {
                $order[] = 'destination';

                return new JsonResponse([]);
            },
        );

        $this->assertSame(
            ['A:before', 'B:before', 'destination', 'B:after', 'A:after'],
            $order->getArrayCopy(),
        );
    }

    #[Test]
    public function middleware_pode_curto_circuitar_sem_chamar_next(): void
    {
        $destinationCalled = false;

        $shortCircuit = new class implements Middleware {
            public function handle(Request $request, \Closure $next): Response
            {
                return new JsonResponse(['blocked' => true], 403);
            }
        };

        $pipeline = new Pipeline([$shortCircuit]);

        $response = $pipeline->process(
            new Request(method: 'GET', path: '/api'),
            static function (Request $request) use (&$destinationCalled): Response {
                $destinationCalled = true;

                return new JsonResponse([]);
            },
        );

        $this->assertFalse($destinationCalled);
        $this->assertSame(403, $response->status());
    }
}
