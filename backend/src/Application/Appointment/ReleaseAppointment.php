<?php

declare(strict_types=1);

namespace App\Application\Appointment;

use App\Application\Shared\ActorContext;
use App\Domain\Appointment\Appointment;
use App\Domain\Appointment\Ports\AppointmentRepository;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\Ports\AuditLogger;

/**
 * Serve tanto o clique manual do painel quanto a rotina automática (`AppointmentLifecycleSweepTask`) --
 * a diferença é só o `$at` que cada chamador passa. `release()` já vira `completed` sozinho.
 */
final readonly class ReleaseAppointment
{
    public function __construct(
        private AppointmentFinder $finder,
        private AppointmentRepository $appointments,
        private AuditLogger $audit,
        private NotifyAppointmentStatusChanged $notify,
    ) {
    }

    public function __invoke(?string $id, \DateTimeImmutable $at, ActorContext $context): Appointment
    {
        $released = $this->finder->findOrFail($id)->release($at);
        $this->appointments->update($released);
        $this->audit->record($context->audits(AuditEvent::AppointmentCompleted, $released->id));
        ($this->notify)($released, 'concluído');

        return $released;
    }
}
