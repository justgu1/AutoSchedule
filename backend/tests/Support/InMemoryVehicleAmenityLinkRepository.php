<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Vehicle\Ports\VehicleAmenityLinkRepository;

final class InMemoryVehicleAmenityLinkRepository implements VehicleAmenityLinkRepository
{
    /** @var array<string, list<string>> */
    private array $links = [];

    public function amenityIdsFor(string $vehicleId): array
    {
        return $this->links[$vehicleId] ?? [];
    }

    public function replace(string $vehicleId, array $amenityIds): void
    {
        $this->links[$vehicleId] = array_values(array_unique($amenityIds));
    }
}
