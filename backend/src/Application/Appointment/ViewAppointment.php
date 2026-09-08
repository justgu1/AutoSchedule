<?php

declare(strict_types=1);

namespace App\Application\Appointment;

use App\Application\Shared\ActorContext;
use App\Domain\Appointment\Appointment;

/** Mesma autorização dupla de confirmar/cancelar -- sessão de dono/admin, ou o token do e-mail. */
final readonly class ViewAppointment
{
    public function __construct(
        private AppointmentFinder $finder,
        private AppointmentAccess $access,
    ) {
    }

    public function __invoke(?string $id, ?string $token, ActorContext $context): Appointment
    {
        $appointment = $this->finder->findOrFail($id);
        $this->access->assertCanActByTokenOrSession($appointment, $token, $context);

        return $appointment;
    }
}
