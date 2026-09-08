<?php

declare(strict_types=1);

namespace App\Domain\Availability\Ports;

use App\Domain\Availability\VehicleAvailabilityRule;

interface VehicleAvailabilityRuleRepository
{
    public function findById(string $id): ?VehicleAvailabilityRule;

    public function insert(VehicleAvailabilityRule $rule): void;

    public function update(VehicleAvailabilityRule $rule): void;

    public function delete(string $id): void;

    /** @return list<VehicleAvailabilityRule> */
    public function findByVehicle(string $vehicleId): array;
}
