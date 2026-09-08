<?php

declare(strict_types=1);

namespace Tests\Domain\Availability;

use App\Domain\Availability\WeeklyWindow;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class WeeklyWindowTest extends TestCase
{
    #[Test]
    public function guarda_weekday_e_horarios_validos(): void
    {
        $window = new WeeklyWindow(2, $this->time('09:00'), $this->time('18:00'));

        $this->assertSame(2, $window->weekday);
    }

    #[Test]
    public function rejeita_weekday_fora_de_0_a_6(): void
    {
        $this->expectException(DomainException::class);

        new WeeklyWindow(7, $this->time('09:00'), $this->time('18:00'));
    }

    #[Test]
    public function rejeita_start_time_maior_ou_igual_a_end_time(): void
    {
        try {
            new WeeklyWindow(2, $this->time('18:00'), $this->time('09:00'));
            $this->fail('Esperava DomainException.');
        } catch (DomainException $exception) {
            $this->assertSame(DomainErrorType::Validation, $exception->type());
        }
    }

    private function time(string $value): \DateTimeImmutable
    {
        $time = \DateTimeImmutable::createFromFormat('!H:i', $value);
        \assert($time instanceof \DateTimeImmutable);

        return $time;
    }
}
