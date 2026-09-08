<?php

declare(strict_types=1);

namespace App\Application\Appointment;

use App\Domain\Appointment\Appointment;
use App\Domain\Appointment\Ports\AppointmentRepository;

/** Só painel -- funcionário confere que o cliente de fato retirou o veículo. Sem evento de auditoria planejado. */
final readonly class PickupAppointment
{
    public function __construct(
        private AppointmentFinder $finder,
        private AppointmentRepository $appointments,
    ) {
    }

    public function __invoke(?string $id): Appointment
    {
        $pickedUp = $this->finder->findOrFail($id)->pickup(new \DateTimeImmutable());
        $this->appointments->update($pickedUp);

        return $pickedUp;
    }
}
