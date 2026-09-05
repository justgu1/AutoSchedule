<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Http;

use App\Infrastructure\Http\HttpException;
use App\Infrastructure\Http\JsonResponse;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\Router;
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
    public function lanca_http_exception_404_quando_nenhuma_rota_bate(): void
    {
        $router = new Router();

        try {
            $router->dispatch(new Request(method: 'GET', path: '/api/inexistente'));
            $this->fail('Expected HttpException to be thrown.');
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->status());
        }
    }

    #[Test]
    public function lanca_http_exception_405_quando_o_path_bate_mas_o_metodo_nao(): void
    {
        $router = new Router();
        $router->get('/api/ping', static fn (Request $request): Response => new JsonResponse(['pong' => true]));

        try {
            $router->dispatch(new Request(method: 'POST', path: '/api/ping'));
            $this->fail('Expected HttpException to be thrown.');
        } catch (HttpException $exception) {
            $this->assertSame(405, $exception->status());
        }
    }

    #[Test]
    public function rejeita_metodo_http_desconhecido_no_registro(): void
    {
        $router = new Router();

        $this->expectException(\InvalidArgumentException::class);

        $router->add('TRACE', '/api/ping', static fn (Request $request): Response => new JsonResponse([]));
    }

    #[Test]
    public function required_roles_devolve_os_roles_declarados_no_registro_da_rota(): void
    {
        $router = new Router();
        $router->get('/api/users', static fn (Request $request): Response => new JsonResponse([]), roles: ['admin']);

        $this->assertSame(['admin'], $router->requiredRoles('GET', '/api/users'));
    }

    #[Test]
    public function required_roles_e_vazio_quando_a_rota_nao_declara_role(): void
    {
        $router = new Router();
        $router->get('/api/ping', static fn (Request $request): Response => new JsonResponse([]));

        $this->assertSame([], $router->requiredRoles('GET', '/api/ping'));
    }

    #[Test]
    public function required_roles_e_vazio_quando_nenhuma_rota_bate(): void
    {
        $router = new Router();

        $this->assertSame([], $router->requiredRoles('GET', '/api/inexistente'));
    }
}
