<?php

declare(strict_types=1);

namespace App\Domain\Appointment\Ports;

use App\Domain\Appointment\Appointment;

interface AppointmentRepository
{
    public function findById(string $id): ?Appointment;

    public function insert(Appointment $appointment): void;

    public function update(Appointment $appointment): void;

    /**
     * @param ?string $ownerUserId escopo do seller; `null` enxerga tudo (admin)
     * @return list<Appointment>
     */
    public function findPage(?string $ownerUserId, ?string $status, ?string $vehicleId, int $limit, int $offset): array;

    public function countPage(?string $ownerUserId, ?string $status, ?string $vehicleId): int;

    /**
     * Horários já ocupados (`pending`/`confirmed`) daquele veículo no intervalo -- pro motor de cálculo.
     *
     * @return list<\DateTimeImmutable>
     */
    public function findOccupiedStarts(string $vehicleId, \DateTimeImmutable $from, \DateTimeImmutable $to): array;

    /** O agendamento anterior daquele veículo, se houver -- decide se o e-mail de confirmação espera handoff. */
    public function findImmediatelyPreceding(string $vehicleId, \DateTimeImmutable $scheduledAt): ?Appointment;

    /** @return list<Appointment> */
    public function findPendingAwaitingConfirmationEmail(): array;

    /** @return list<Appointment> */
    public function findOverduePendingConfirmation(\DateTimeImmutable $now): array;

    /**
     * `confirmed` cujo prazo de devolução (scheduled_at + 60min) já passou -- release/no-show automáticos.
     *
     * @return list<Appointment>
     */
    public function findOverdueConfirmed(\DateTimeImmutable $now): array;
}
