<?php

declare(strict_types=1);

namespace Tests\Domain\Exceptions;

use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DomainExceptionTest extends TestCase
{
    #[Test]
    public function expoe_a_mensagem_e_o_tipo(): void
    {
        $exception = new DomainException('Vehicle not found.', DomainErrorType::NotFound);

        $this->assertSame('Vehicle not found.', $exception->getMessage());
        $this->assertSame(DomainErrorType::NotFound, $exception->type());
    }

    #[Test]
    public function expoe_os_erros_por_campo_quando_e_de_validacao(): void
    {
        $exception = new DomainException(
            'Invalid data.',
            DomainErrorType::Validation,
            ['id' => 'Vehicle ID must be a valid integer.'],
        );

        $this->assertSame(['id' => 'Vehicle ID must be a valid integer.'], $exception->errors());
    }

    #[Test]
    public function errors_e_vazio_quando_nao_informado(): void
    {
        $exception = new DomainException('Conflict.', DomainErrorType::Conflict);

        $this->assertSame([], $exception->errors());
    }
}
