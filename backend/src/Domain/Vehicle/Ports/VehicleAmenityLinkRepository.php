<?php

declare(strict_types=1);

namespace App\Domain\Vehicle\Ports;

interface VehicleAmenityLinkRepository
{
    /** @return list<string> amenity ids ligados ao veículo */
    public function amenityIdsFor(string $vehicleId): array;

    /**
     * Substitui o conjunto inteiro -- espelha o "manda a lista toda" de `reorderVehiclePhotos`.
     *
     * @param list<string> $amenityIds
     */
    public function replace(string $vehicleId, array $amenityIds): void;
}
