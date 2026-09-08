<?php

declare(strict_types=1);

namespace App\Application\Appointment;

use App\Application\Shared\ActorContext;
use App\Domain\Appointment\Appointment;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;

/**
 * Confirmar/cancelar aceita sessão de admin/dono OU o token do e-mail, dois jeitos de autorizar a
 * mesma ação. Sessão já passou pelo RLS pra achar a linha; sem sessão, só o token bate.
 */
final readonly class AppointmentAccess
{
    public function assertCanActByTokenOrSession(Appointment $appointment, ?string $token, ActorContext $context): void
    {
        if ($context->actorId !== null) {
            return;
        }

        if ($token === null || $appointment->confirmationTokenHash === null
            || !hash_equals($appointment->confirmationTokenHash, hash('sha256', $token))) {
            throw new DomainException('Appointment not found.', DomainErrorType::NotFound);
        }
    }
}
