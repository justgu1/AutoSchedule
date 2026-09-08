<?php

declare(strict_types=1);

namespace App\Application\Appointment;

use App\Application\Shared\ActorContext;
use App\Domain\Appointment\Appointment;
use App\Domain\Appointment\Ports\AppointmentRepository;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\Ports\AuditLogger;

final readonly class CancelAppointment
{
    public function __construct(
        private AppointmentFinder $finder,
        private AppointmentRepository $appointments,
        private AppointmentAccess $access,
        private AuditLogger $audit,
        private NotifyAppointmentStatusChanged $notify,
    ) {
    }

    public function __invoke(?string $id, ?string $token, ActorContext $context): Appointment
    {
        $appointment = $this->finder->findOrFail($id);
        $this->access->assertCanActByTokenOrSession($appointment, $token, $context);

        $cancelled = $appointment->cancel();
        $this->appointments->update($cancelled);
        $this->audit->record($context->audits(AuditEvent::AppointmentCancelled, $cancelled->id));
        ($this->notify)($cancelled, 'cancelado');

        return $cancelled;
    }
}
