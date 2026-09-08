<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Http;

use App\Domain\User\UserRole;
use App\Infrastructure\Container\Container;
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
        $router = $this->router();
        $router->get('/api/ping', static fn (Request $request): Response => new JsonResponse(['pong' => true]));

        $response = $router->dispatch($this->resolved($router, 'GET', '/api/ping'));

        $this->assertSame(200, $response->status());
        $this->assertSame('{"pong":true}', $response->body());
    }

    #[Test]
    public function extrai_parametros_de_rota(): void
    {
        $router = $this->router();
        $router->get('/api/ping/{id}', static fn (Request $request): Response => new JsonResponse(['id' => $request->param('id')]));

        $response = $router->dispatch($this->resolved($router, 'GET', '/api/ping/42'));

        $this->assertSame('{"id":"42"}', $response->body());
    }

    #[Test]
    public function trata_path_com_e_sem_barra_final_como_a_mesma_rota(): void
    {
        $router = $this->router();
        $router->get('/api/ping', static fn (Request $request): Response => new JsonResponse(['pong' => true]));

        $this->assertSame(200, $router->dispatch($this->resolved($router, 'GET', '/api/ping/'))->status());
    }

    #[Test]
    public function despacha_para_um_controller_resolvido_do_container(): void
    {
        $router = $this->router();
        $router->get('/api/ping', [PingControllerFixture::class, 'show']);

        $this->assertSame('{"path":"\\/api\\/ping"}', $router->dispatch($this->resolved($router, 'GET', '/api/ping'))->body());
    }

    #[Test]
    public function lanca_http_exception_404_quando_nenhuma_rota_bate(): void
    {
        $router = $this->router();

        try {
            $router->dispatch($this->resolved($router, 'GET', '/api/inexistente'));
            $this->fail('Expected HttpException to be thrown.');
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->status());
        }
    }

    #[Test]
    public function lanca_http_exception_405_quando_o_path_bate_mas_o_metodo_nao(): void
    {
        $router = $this->router();
        $router->get('/api/ping', static fn (Request $request): Response => new JsonResponse(['pong' => true]));

        try {
            $router->dispatch($this->resolved($router, 'POST', '/api/ping'));
            $this->fail('Expected HttpException to be thrown.');
        } catch (HttpException $exception) {
            $this->assertSame(405, $exception->status());
        }
    }

    #[Test]
    public function match_devolve_a_rota_com_o_que_foi_declarado_no_fluente(): void
    {
        $router = $this->router();
        $router->post('/api/oauth/token', static fn (Request $request): Response => new JsonResponse([]))
            ->serviceContext()
            ->rateLimit('auth')
            ->accepts('client_id', 'email');

        $route = $router->match('POST', '/api/oauth/token');

        $this->assertNotNull($route);
        $this->assertTrue($route->needsServiceContext());
        $this->assertSame('auth', $route->rateLimitPolicy());
        $this->assertSame(['client_id', 'email'], $route->acceptedFields());
        $this->assertSame([], $route->requiredRoles());
    }

    #[Test]
    public function match_devolve_null_quando_nenhuma_rota_bate(): void
    {
        $this->assertNull($this->router()->match('GET', '/api/inexistente'));
    }

    #[Test]
    public function group_aplica_os_roles_a_toda_rota_registrada_dentro(): void
    {
        $router = $this->router();
        $router->group([UserRole::Admin], static function (Router $router): void {
            $router->get('/api/users', static fn (Request $request): Response => new JsonResponse([]));
        });
        $router->get('/api/ping', static fn (Request $request): Response => new JsonResponse([]));

        $this->assertSame([UserRole::Admin], $router->match('GET', '/api/users')?->requiredRoles());
        $this->assertSame([], $router->match('GET', '/api/ping')?->requiredRoles());
    }

    #[Test]
    public function catalog_lista_toda_rota_registrada(): void
    {
        $router = $this->router();
        $router->get('/api/ping', static fn (Request $request): Response => new JsonResponse([]))
            ->describes('Health check.');
        $router->group([UserRole::Admin], static function (Router $router): void {
            $router->post('/api/users', static fn (Request $request): Response => new JsonResponse([]))
                ->describes('Creates a user.')
                ->accepts('name', 'email');
        });

        $this->assertSame(
            [
                ['path' => '/api/ping', 'methods' => ['GET'], 'description' => 'Health check.', 'accepts' => [], 'roles' => []],
                ['path' => '/api/users', 'methods' => ['POST'], 'description' => 'Creates a user.', 'accepts' => ['name', 'email'], 'roles' => ['admin']],
            ],
            $router->catalog(),
        );
    }

    private function router(): Router
    {
        return new Router(new Container());
    }

    /** O pipeline casa a rota antes do dispatch, então o teste faz o mesmo. */
    private function resolved(Router $router, string $method, string $path): Request
    {
        return new Request(method: $method, path: $path)
            ->withAttribute('route', $router->match($method, $path));
    }
}

final class PingControllerFixture
{
    public function show(Request $request): Response
    {
        return new JsonResponse(['path' => $request->path()]);
    }
}
