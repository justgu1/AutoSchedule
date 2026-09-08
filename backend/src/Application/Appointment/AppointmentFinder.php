<?php

declare(strict_types=1);

namespace App\Application\Appointment;

use App\Domain\Appointment\Appointment;
use App\Domain\Appointment\Ports\AppointmentRepository;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;

/** "Não é seu" e "não existe" viram o mesmo 404 -- o RLS já esconde a linha alheia. */
final readonly class AppointmentFinder
{
    public function __construct(private AppointmentRepository $appointments)
    {
    }

    public function findOrFail(?string $id): Appointment
    {
        $appointment = $id !== null ? $this->appointments->findById($id) : null;

        if (!$appointment instanceof Appointment) {
            throw new DomainException('Appointment not found.', DomainErrorType::NotFound);
        }

        return $appointment;
    }
}
