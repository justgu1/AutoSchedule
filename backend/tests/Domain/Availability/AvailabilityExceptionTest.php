<?php

declare(strict_types=1);

namespace Tests\Domain\Availability;

use App\Domain\Availability\AvailabilityException;
use App\Domain\Exceptions\DomainException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AvailabilityExceptionTest extends TestCase
{
    #[Test]
    public function register_aceita_escopo_de_concessionaria_sozinho(): void
    {
        $exception = AvailabilityException::register('d1', null, $this->date(), null, null, false, 'Feriado');

        $this->assertSame('d1', $exception->dealershipId);
        $this->assertNull($exception->vehicleId);
    }

    #[Test]
    public function rejeita_os_dois_escopos_ao_mesmo_tempo(): void
    {
        $this->expectException(DomainException::class);

        AvailabilityException::register('d1', 'v1', $this->date(), null, null, false, null);
    }

    #[Test]
    public function rejeita_nenhum_escopo(): void
    {
        $this->expectException(DomainException::class);

        AvailabilityException::register(null, null, $this->date(), null, null, false, null);
    }

    #[Test]
    public function rejeita_horario_especial_sem_start_e_end(): void
    {
        $this->expectException(DomainException::class);

        AvailabilityException::register('d1', null, $this->date(), null, null, true, null);
    }

    #[Test]
    public function aceita_bloqueio_total_sem_horario(): void
    {
        $exception = AvailabilityException::register('d1', null, $this->date(), null, null, false, 'Fechado');

        $this->assertFalse($exception->isAvailable);
    }

    private function date(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-09-08');
    }
}
