<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Vehicle\Amenity;
use App\Domain\Vehicle\Ports\VehicleAmenityCatalog;

final readonly class InMemoryVehicleAmenityCatalog implements VehicleAmenityCatalog
{
    /** @param list<Amenity> $items */
    public function __construct(private array $items = [])
    {
    }

    public function all(): array
    {
        return $this->items;
    }

    public function existingIds(array $ids): array
    {
        $known = array_map(static fn (Amenity $a): string => $a->id, $this->items);

        return array_values(array_intersect(array_unique($ids), $known));
    }
}
