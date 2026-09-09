<?php

declare(strict_types=1);

namespace App\Application\Vehicle;

use App\Domain\Vehicle\Amenity;

final readonly class ListVehicleAmenityCatalog
{
    public function __construct(private VehicleAmenities $amenities)
    {
    }

    /** @return list<Amenity> */
    public function __invoke(): array
    {
        return $this->amenities->catalog();
    }
}
