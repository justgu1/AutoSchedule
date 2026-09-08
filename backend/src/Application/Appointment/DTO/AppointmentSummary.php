<?php

declare(strict_types=1);

namespace App\Application\Appointment\DTO;

use App\Domain\Appointment\Appointment;

/** Nunca expõe `confirmationTokenHash` -- é segredo entre o e-mail e o clique do cliente. */
final readonly class AppointmentSummary
{
    public function __construct(
        public string $id,
        public string $vehicleId,
        public \DateTimeImmutable $scheduledAt,
        public int $durationMinutes,
        public string $customerName,
        public string $customerEmail,
        public string $customerPhone,
        public string $status,
        public ?\DateTimeImmutable $pickedUpAt,
        public ?\DateTimeImmutable $releasedAt,
        public \DateTimeImmutable $createdAt,
    ) {
    }

    public static function fromAppointment(Appointment $appointment): self
    {
        return new self(
            id: $appointment->id,
            vehicleId: $appointment->vehicleId,
            scheduledAt: $appointment->scheduledAt,
            durationMinutes: Appointment::DURATION_MINUTES,
            customerName: $appointment->customerName,
            customerEmail: $appointment->customerEmail->value,
            customerPhone: $appointment->customerPhone,
            status: $appointment->status->value,
            pickedUpAt: $appointment->pickedUpAt,
            releasedAt: $appointment->releasedAt,
            createdAt: $appointment->createdAt,
        );
    }

    /** @return array{id: string, vehicle_id: string, scheduled_at: string, duration_minutes: int, customer_name: string, customer_email: string, customer_phone: string, status: string, picked_up_at: ?string, released_at: ?string, created_at: string} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'vehicle_id' => $this->vehicleId,
            'scheduled_at' => $this->scheduledAt->format(DATE_ATOM),
            'duration_minutes' => $this->durationMinutes,
            'customer_name' => $this->customerName,
            'customer_email' => $this->customerEmail,
            'customer_phone' => $this->customerPhone,
            'status' => $this->status,
            'picked_up_at' => $this->pickedUpAt?->format(DATE_ATOM),
            'released_at' => $this->releasedAt?->format(DATE_ATOM),
            'created_at' => $this->createdAt->format(DATE_ATOM),
        ];
    }
}
