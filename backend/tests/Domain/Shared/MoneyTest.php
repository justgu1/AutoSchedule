<?php

declare(strict_types=1);

namespace Tests\Domain\Shared;

use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Domain\Shared\Money;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    #[Test]
    public function from_decimal_guarda_o_valor_em_centavos_sem_perder_precisao(): void
    {
        $this->assertSame(8990099, Money::fromDecimal('89900.99')->cents);
        $this->assertSame(8990050, Money::fromDecimal('89900.5')->cents);
        $this->assertSame(8990000, Money::fromDecimal('89900')->cents);
    }

    #[Test]
    public function to_decimal_devolve_sempre_duas_casas(): void
    {
        $this->assertSame('89900.00', new Money(8990000)->toDecimal());
        $this->assertSame('0.07', new Money(7)->toDecimal());
    }

    #[Test]
    public function from_decimal_rejeita_valor_negativo(): void
    {
        $this->expectException(DomainException::class);

        Money::fromDecimal('-1.00');
    }

    #[Test]
    public function from_decimal_rejeita_mais_de_duas_casas_decimais(): void
    {
        try {
            Money::fromDecimal('10.999');
            $this->fail('Expected a validation error.');
        } catch (DomainException $exception) {
            $this->assertSame(DomainErrorType::Validation, $exception->type());
        }
    }

    #[Test]
    public function construtor_rejeita_centavos_negativos(): void
    {
        $this->expectException(DomainException::class);

        new Money(-1);
    }
}
