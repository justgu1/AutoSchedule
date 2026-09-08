<?php

declare(strict_types=1);

namespace App\Application\Appointment;

use App\Application\Shared\ActorContext;
use App\Domain\Appointment\Appointment;
use App\Domain\Appointment\Ports\AppointmentRepository;

final readonly class ListAppointments
{
    public function __construct(private AppointmentRepository $appointments)
    {
    }

    /** @return array{items: list<Appointment>, total: int} */
    public function __invoke(ActorContext $context, ?string $status, ?string $vehicleId, int $limit, int $offset): array
    {
        $ownerUserId = $context->isAdmin() ? null : $context->actorId;

        return [
            'items' => $this->appointments->findPage($ownerUserId, $status, $vehicleId, $limit, $offset),
            'total' => $this->appointments->countPage($ownerUserId, $status, $vehicleId),
        ];
    }
}
