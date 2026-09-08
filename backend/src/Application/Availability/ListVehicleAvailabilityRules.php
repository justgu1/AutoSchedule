<?php

declare(strict_types=1);

namespace App\Application\Availability;

use App\Application\Vehicle\VehicleFinder;
use App\Domain\Availability\Ports\VehicleAvailabilityRuleRepository;
use App\Domain\Availability\VehicleAvailabilityRule;

final readonly class ListVehicleAvailabilityRules
{
    public function __construct(
        private VehicleFinder $vehicles,
        private VehicleAvailabilityRuleRepository $rules,
    ) {
    }

    /** @return list<VehicleAvailabilityRule> */
    public function __invoke(?string $vehicleId): array
    {
        $vehicle = $this->vehicles->findOrFail($vehicleId);

        return $this->rules->findByVehicle($vehicle->id);
    }
}
