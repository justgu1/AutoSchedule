<?php

declare(strict_types=1);

namespace App\Application\Vehicle;

use App\Application\Vehicle\DTO\VehicleProfile;

final readonly class ViewVehicle
{
    public function __construct(private VehicleFinder $finder)
    {
    }

    public function __invoke(?string $id): VehicleProfile
    {
        return VehicleProfile::fromVehicle($this->finder->findOrFail($id));
    }
}
