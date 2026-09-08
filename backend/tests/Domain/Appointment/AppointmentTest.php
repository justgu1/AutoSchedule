<?php

declare(strict_types=1);

namespace Tests\Domain\Appointment;

use App\Domain\Appointment\Appointment;
use App\Domain\Appointment\AppointmentStatus;
use App\Domain\Exceptions\DomainException;
use App\Domain\Shared\Email;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AppointmentTest extends TestCase
{
    #[Test]
    public function request_monta_um_agendamento_pending_sem_token_de_confirmacao_ainda(): void
    {
        $appointment = $this->requestFixture();

        $this->assertSame(AppointmentStatus::Pending, $appointment->status);
        $this->assertNull($appointment->confirmationTokenHash);
        $this->assertNull($appointment->confirmationEmailSentAt);
        $this->assertNull($appointment->expiresAt);
        $this->assertNull($appointment->pickedUpAt);
        $this->assertNull($appointment->releasedAt);
    }

    #[Test]
    public function return_deadline_e_sempre_scheduled_at_mais_60_minutos(): void
    {
        $appointment = $this->requestFixture();

        $this->assertEquals($appointment->scheduledAt->modify('+60 minutes'), $appointment->returnDeadline());
    }

    #[Test]
    public function mark_confirmation_sent_gera_o_token_e_grava_o_prazo_a_partir_do_envio(): void
    {
        $appointment = $this->requestFixture();
        $now = new \DateTimeImmutable('2026-09-08 09:00');

        [$rawToken, $sent] = $appointment->markConfirmationSent($now, 1800);

        $this->assertSame(hash('sha256', $rawToken), $sent->confirmationTokenHash);
        $this->assertSame($now, $sent->confirmationEmailSentAt);
        $this->assertEquals($now->modify('+1800 seconds'), $sent->expiresAt);
    }

    #[Test]
    public function mark_confirmation_sent_duas_vezes_e_conflito(): void
    {
        [, $sent] = $this->requestFixture()->markConfirmationSent(new \DateTimeImmutable(), 1800);

        $this->expectException(DomainException::class);
        (void) $sent->markConfirmationSent(new \DateTimeImmutable(), 1800);
    }

    #[Test]
    public function confirm_move_pending_pra_confirmed(): void
    {
        $appointment = $this->requestFixture();

        $this->assertSame(AppointmentStatus::Confirmed, $appointment->confirm()->status);
    }

    #[Test]
    public function cancel_move_pending_pra_cancelled(): void
    {
        $appointment = $this->requestFixture();

        $this->assertSame(AppointmentStatus::Cancelled, $appointment->cancel()->status);
    }

    #[Test]
    public function cancel_depois_de_confirmado_e_rejeitado(): void
    {
        $confirmed = $this->requestFixture()->confirm();

        $this->expectException(DomainException::class);
        (void) $confirmed->cancel();
    }

    #[Test]
    public function pickup_so_funciona_a_partir_de_confirmed(): void
    {
        $this->expectException(DomainException::class);
        (void) $this->requestFixture()->pickup(new \DateTimeImmutable());
    }

    #[Test]
    public function pickup_grava_o_horario_e_mantem_confirmed(): void
    {
        $now = new \DateTimeImmutable('2026-09-08 10:15');

        $pickedUp = $this->requestFixture()->confirm()->pickup($now);

        $this->assertSame($now, $pickedUp->pickedUpAt);
        $this->assertSame(AppointmentStatus::Confirmed, $pickedUp->status);
    }

    #[Test]
    public function pickup_duas_vezes_e_conflito(): void
    {
        $pickedUp = $this->requestFixture()->confirm()->pickup(new \DateTimeImmutable());

        $this->expectException(DomainException::class);
        (void) $pickedUp->pickup(new \DateTimeImmutable());
    }

    #[Test]
    public function release_sem_pickup_e_rejeitado(): void
    {
        $confirmed = $this->requestFixture()->confirm();

        $this->expectException(DomainException::class);
        (void) $confirmed->release(new \DateTimeImmutable());
    }

    #[Test]
    public function release_depois_de_pickup_completa_o_agendamento(): void
    {
        $appointment = $this->requestFixture();
        $deadline = $appointment->returnDeadline();
        $pickedUp = $appointment->confirm()->pickup(new \DateTimeImmutable('2026-09-08 10:15'));

        $released = $pickedUp->release($deadline);

        $this->assertSame($deadline, $released->releasedAt);
        $this->assertSame(AppointmentStatus::Completed, $released->status);
    }

    #[Test]
    public function mark_no_show_so_funciona_sem_pickup(): void
    {
        $pickedUp = $this->requestFixture()->confirm()->pickup(new \DateTimeImmutable());

        $this->expectException(DomainException::class);
        (void) $pickedUp->markNoShow();
    }

    #[Test]
    public function mark_no_show_move_confirmed_pra_no_show(): void
    {
        $appointment = $this->requestFixture();

        $this->assertSame(AppointmentStatus::NoShow, $appointment->confirm()->markNoShow()->status);
    }

    private function requestFixture(): Appointment
    {
        return Appointment::request(
            vehicleId: 'vehicle-1',
            userId: 'user-1',
            scheduledAt: new \DateTimeImmutable('2026-09-08 10:00'),
            customerName: 'Ada Lovelace',
            customerEmail: new Email('ada@example.com'),
            customerPhone: '11999990000',
        );
    }
}
