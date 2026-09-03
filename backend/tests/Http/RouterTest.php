<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Http\JsonResponse;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    #[Test]
    public function despacha_para_a_rota_registrada(): void
    {
        $router = new Router();
        $router->get('/api/ping', static fn (Request $request): Response => new JsonResponse(['pong' => true]));

        $response = $router->dispatch(new Request(method: 'GET', path: '/api/ping'));

        $this->assertSame(200, $response->status());
        $this->assertSame('{"pong":true}', $response->body());
    }

    #[Test]
    public function extrai_parametros_de_rota(): void
    {
        $router = new Router();
        $router->get(
            '/api/ping/{id}',
            static fn (Request $request): Response => new JsonResponse(['id' => $request->param('id')]),
        );

        $response = $router->dispatch(new Request(method: 'GET', path: '/api/ping/42'));

        $this->assertSame('{"id":"42"}', $response->body());
    }

    #[Test]
    public function trata_path_com_e_sem_barra_final_como_a_mesma_rota(): void
    {
        $router = new Router();
        $router->get('/api/ping', static fn (Request $request): Response => new JsonResponse(['pong' => true]));

        $comBarra = $router->dispatch(new Request(method: 'GET', path: '/api/ping/'));

        $this->assertSame(200, $comBarra->status());
    }

    #[Test]
    public function devolve_404_quando_nenhuma_rota_bate(): void
    {
        $router = new Router();

        $response = $router->dispatch(new Request(method: 'GET', path: '/api/inexistente'));

        $this->assertSame(404, $response->status());
    }

    #[Test]
    public function devolve_405_quando_o_path_bate_mas_o_metodo_nao(): void
    {
        $router = new Router();
        $router->get('/api/ping', static fn (Request $request): Response => new JsonResponse(['pong' => true]));

        $response = $router->dispatch(new Request(method: 'POST', path: '/api/ping'));

        $this->assertSame(405, $response->status());
    }

    #[Test]
    public function rejeita_metodo_http_desconhecido_no_registro(): void
    {
        $router = new Router();

        $this->expectException(\InvalidArgumentException::class);

        $router->add('TRACE', '/api/ping', static fn (Request $request): Response => new JsonResponse([]));
    }
}
