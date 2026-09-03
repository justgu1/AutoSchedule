<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Http;

use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Infrastructure\Http\ExceptionHandler;
use App\Infrastructure\Http\HttpException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ExceptionHandlerTest extends TestCase
{
    #[Test]
    public function http_exception_usa_o_proprio_status(): void
    {
        $handler = new ExceptionHandler();

        $response = $handler->handle(HttpException::notFound());

        $this->assertSame(404, $response->status());
        $this->assertSame('{"message":"Not Found"}', $response->body());
    }

    #[Test]
    public function domain_exception_not_found_vira_404(): void
    {
        $handler = new ExceptionHandler();

        $response = $handler->handle(new DomainException('Vehicle not found.', DomainErrorType::NotFound));

        $this->assertSame(404, $response->status());
    }

    #[Test]
    public function domain_exception_validation_vira_422_com_os_erros_por_campo(): void
    {
        $handler = new ExceptionHandler();

        $response = $handler->handle(new DomainException(
            'Invalid data.',
            DomainErrorType::Validation,
            ['id' => 'must be an integer'],
        ));

        $this->assertSame(422, $response->status());
        $this->assertSame('{"message":"Invalid data.","errors":{"id":"must be an integer"}}', $response->body());
    }

    #[Test]
    public function domain_exception_conflict_vira_409(): void
    {
        $handler = new ExceptionHandler();

        $response = $handler->handle(new DomainException('Slot already booked.', DomainErrorType::Conflict));

        $this->assertSame(409, $response->status());
    }

    #[Test]
    public function excecao_nao_mapeada_vira_500_generico_quando_debug_desligado(): void
    {
        $handler = new ExceptionHandler(debug: false);

        $response = $handler->handle(new \RuntimeException('detalhe interno sensivel'));

        $this->assertSame(500, $response->status());
        $this->assertSame('{"message":"Internal Server Error"}', $response->body());
    }

    #[Test]
    public function excecao_nao_mapeada_expoe_a_mensagem_quando_debug_ligado(): void
    {
        $handler = new ExceptionHandler(debug: true);

        $response = $handler->handle(new \RuntimeException('detalhe interno'));

        $this->assertSame('{"message":"detalhe interno"}', $response->body());
    }
}
