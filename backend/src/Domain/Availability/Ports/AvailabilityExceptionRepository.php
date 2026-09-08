<?php

declare(strict_types=1);

namespace App\Domain\Availability\Ports;

use App\Domain\Availability\AvailabilityException;

interface AvailabilityExceptionRepository
{
    public function findById(string $id): ?AvailabilityException;

    public function insert(AvailabilityException $exception): void;

    public function update(AvailabilityException $exception): void;

    public function delete(string $id): void;

    /** @return list<AvailabilityException> pra gestão no painel, sem recorte de data */
    public function findByDealership(string $dealershipId): array;

    /** @return list<AvailabilityException> */
    public function findByVehicle(string $vehicleId): array;

    /** @return list<AvailabilityException> pro cálculo de disponibilidade, um intervalo de datas por vez */
    public function findByDealershipInRange(string $dealershipId, \DateTimeImmutable $from, \DateTimeImmutable $to): array;

    /** @return list<AvailabilityException> */
    public function findByVehicleInRange(string $vehicleId, \DateTimeImmutable $from, \DateTimeImmutable $to): array;
}
