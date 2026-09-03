<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    private array $serverBackup;
    private array $getBackup;

    protected function setUp(): void
    {
        $this->serverBackup = $_SERVER;
        $this->getBackup = $_GET;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        $_GET = $this->getBackup;
    }

    #[Test]
    public function from_globals_normaliza_o_metodo_para_maiusculo(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'post';
        $_SERVER['PATH_INFO'] = '/api/ping';

        $request = Request::fromGlobals(rawBody: '');

        $this->assertSame('POST', $request->method());
    }

    #[Test]
    public function from_globals_resolve_o_path_sem_a_query_string(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['PATH_INFO'] = '/api/ping?foo=bar';

        $request = Request::fromGlobals();

        $this->assertSame('/api/ping', $request->path());
    }

    #[Test]
    public function from_globals_expoe_a_query_string_via_query(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['PATH_INFO'] = '/api/ping';
        $_GET = ['foo' => 'bar'];

        $request = Request::fromGlobals();

        $this->assertSame('bar', $request->query('foo'));
    }

    #[Test]
    public function from_globals_le_headers_http_via_fallback(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['PATH_INFO'] = '/api/ping';
        $_SERVER['HTTP_X_CUSTOM'] = 'valor';

        $request = Request::fromGlobals();

        $this->assertSame('valor', $request->header('X-Custom'));
    }

    #[Test]
    public function from_globals_decodifica_o_corpo_recebido_como_json(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['PATH_INFO'] = '/api/ping';

        $request = Request::fromGlobals(rawBody: '{"a":1}');

        $this->assertSame(['a' => 1], $request->json());
    }

    #[Test]
    public function normaliza_barra_final_do_path(): void
    {
        $this->assertSame('/api', Request::normalizePath('/api/'));
        $this->assertSame('/api', Request::normalizePath('/api'));
        $this->assertSame('/', Request::normalizePath('/'));
        $this->assertSame('/', Request::normalizePath(''));
    }

    #[Test]
    public function with_params_nao_muta_a_instancia_original(): void
    {
        $request = new Request(method: 'GET', path: '/api/ping/{id}');
        $comParams = $request->withParams(['id' => '42']);

        $this->assertNull($request->param('id'));
        $this->assertSame('42', $comParams->param('id'));
        $this->assertSame(['id' => '42'], $comParams->params());
    }
}
