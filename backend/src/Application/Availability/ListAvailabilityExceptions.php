<?php

declare(strict_types=1);

namespace App\Application\Availability;

use App\Application\Dealership\DealershipFinder;
use App\Application\Vehicle\VehicleFinder;
use App\Domain\Availability\AvailabilityException;
use App\Domain\Availability\Ports\AvailabilityExceptionRepository;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;

final readonly class ListAvailabilityExceptions
{
    public function __construct(
        private DealershipFinder $dealerships,
        private VehicleFinder $vehicles,
        private AvailabilityExceptionRepository $exceptions,
    ) {
    }

    /** @return list<AvailabilityException> */
    public function __invoke(?string $dealershipId, ?string $vehicleId): array
    {
        if ($dealershipId !== null) {
            return $this->exceptions->findByDealership($this->dealerships->findOrFail($dealershipId)->id);
        }

        if ($vehicleId !== null) {
            return $this->exceptions->findByVehicle($this->vehicles->findOrFail($vehicleId)->id);
        }

        throw new DomainException('Invalid data.', DomainErrorType::Validation, ['dealership_id' => 'Provide dealership_id or vehicle_id.']);
    }
}
