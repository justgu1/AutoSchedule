<?php

declare(strict_types=1);

namespace App\Infrastructure\Scheduler;

use App\Application\Appointment\NotifyAppointmentStatusChanged;
use App\Application\Ports\Transaction;
use App\Domain\Appointment\Appointment;
use App\Domain\Appointment\Ports\AppointmentRepository;
use App\Domain\Audit\AuditEntry;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\Ports\AuditLogger;

/** Reaproveita `AppointmentCancelled` (não é um evento novo) -- `context.via` distingue de um cancelamento pedido. */
final readonly class ExpirePendingAppointmentsTask implements ScheduledTask
{
    public function __construct(
        private AppointmentRepository $appointments,
        private Transaction $transaction,
        private AuditLogger $audit,
        private NotifyAppointmentStatusChanged $notify,
    ) {
    }

    public function name(): string
    {
        return 'expire-pending-appointments';
    }

    public function dueIntervalSeconds(): int
    {
        return 60;
    }

    public function run(): void
    {
        $now = new \DateTimeImmutable();

        foreach ($this->appointments->findOverduePendingConfirmation($now) as $appointment) {
            $cancelled = $this->transaction->run(function () use ($appointment): Appointment {
                $cancelled = $appointment->cancel();
                $this->appointments->update($cancelled);

                return $cancelled;
            });

            $this->audit->record(new AuditEntry(AuditEvent::AppointmentCancelled, auditableId: $cancelled->id, context: ['via' => 'expired']));
            ($this->notify)($cancelled, 'cancelado (prazo de confirmação expirou)');
        }
    }
}
