<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Http;

use App\Infrastructure\Http\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase
{
    #[Test]
    public function with_header_devolve_uma_nova_instancia_sem_mutar_a_original(): void
    {
        $response = new Response(body: 'ok', status: 200);
        $comHeader = $response->withHeader('X-Foo', 'bar');

        $this->assertSame([], $response->headers());
        $this->assertSame(['X-Foo' => 'bar'], $comHeader->headers());
    }

    #[Test]
    public function rejeita_header_com_quebra_de_linha(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Response(headers: ["X-Foo" => "bar\r\nX-Injected: 1"]);
    }

    #[Test]
    public function rejeita_nome_de_header_com_quebra_de_linha_no_with_header(): void
    {
        $response = new Response();

        $this->expectException(\InvalidArgumentException::class);

        $response->withHeader("X-Foo\r\nX-Injected", '1');
    }

    #[Test]
    public function success_encapsula_o_dado_em_data(): void
    {
        $response = Response::success(['id' => 1]);

        $this->assertSame('{"data":{"id":1}}', $response->body());
        $this->assertSame(200, $response->status());
    }

    #[Test]
    public function error_expoe_a_mensagem_sem_errors_quando_nao_informado(): void
    {
        $response = Response::error('Not Found', 404);

        $this->assertSame('{"message":"Not Found"}', $response->body());
        $this->assertSame(404, $response->status());
    }

    #[Test]
    public function error_inclui_errors_por_campo_quando_informado(): void
    {
        $response = Response::error('Invalid data.', 422, ['id' => 'must be an integer']);

        $this->assertSame('{"message":"Invalid data.","errors":{"id":"must be an integer"}}', $response->body());
    }

    #[Test]
    public function paginated_inclui_data_e_meta_com_last_page_calculado(): void
    {
        $response = Response::paginated(['a', 'b'], page: 2, perPage: 2, total: 5);

        $this->assertSame(
            '{"data":["a","b"],"meta":{"page":2,"per_page":2,"total":5,"last_page":3}}',
            $response->body(),
        );
    }

    #[Test]
    public function paginated_last_page_e_pelo_menos_1_mesmo_sem_resultado(): void
    {
        $response = Response::paginated([], page: 1, perPage: 20, total: 0);

        $this->assertSame(1, json_decode($response->body(), true)['meta']['last_page']);
    }
}
