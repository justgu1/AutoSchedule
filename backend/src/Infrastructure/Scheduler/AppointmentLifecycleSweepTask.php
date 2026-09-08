<?php

declare(strict_types=1);

namespace App\Infrastructure\Scheduler;

use App\Application\Appointment\ReleaseAppointment;
use App\Application\Ports\Transaction;
use App\Application\Shared\ActorContext;
use App\Domain\Appointment\Appointment;
use App\Domain\Appointment\Ports\AppointmentRepository;

/**
 * `confirmed` cujo prazo de devolução passou: libera (via `ReleaseAppointment`, mesmo caminho do
 * clique manual) se já tinha sido retirado; senão `no_show`, sem evento de auditoria planejado.
 */
final readonly class AppointmentLifecycleSweepTask implements ScheduledTask
{
    public function __construct(
        private AppointmentRepository $appointments,
        private ReleaseAppointment $release,
        private Transaction $transaction,
    ) {
    }

    public function name(): string
    {
        return 'appointment-lifecycle-sweep';
    }

    public function dueIntervalSeconds(): int
    {
        return 60;
    }

    public function run(): void
    {
        $now = new \DateTimeImmutable();
        $context = new ActorContext();

        foreach ($this->appointments->findOverdueConfirmed($now) as $appointment) {
            if ($appointment->pickedUpAt instanceof \DateTimeImmutable) {
                // Ancorado no prazo de devolução, não em `$now`: mantém o buffer de 15min determinístico.
                $this->transaction->run(fn (): Appointment => ($this->release)($appointment->id, $appointment->returnDeadline(), $context));

                continue;
            }

            $this->transaction->run(function () use ($appointment): Appointment {
                $noShow = $appointment->markNoShow();
                $this->appointments->update($noShow);

                return $noShow;
            });
        }
    }
}
