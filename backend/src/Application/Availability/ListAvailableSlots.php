<?php

declare(strict_types=1);

namespace App\Application\Availability;

use App\Application\Dealership\DealershipFinder;
use App\Application\Vehicle\VehicleFinder;
use App\Domain\Appointment\Appointment;
use App\Domain\Appointment\Ports\AppointmentRepository;
use App\Domain\Availability\AvailabilityCalculator;
use App\Domain\Availability\DealershipAvailabilityRule;
use App\Domain\Availability\Ports\AvailabilityExceptionRepository;
use App\Domain\Availability\Ports\DealershipAvailabilityRuleRepository;
use App\Domain\Availability\Ports\VehicleAvailabilityRuleRepository;
use App\Domain\Availability\VehicleAvailabilityRule;

final readonly class ListAvailableSlots
{
    public function __construct(
        private VehicleFinder $vehicles,
        private DealershipFinder $dealerships,
        private DealershipAvailabilityRuleRepository $dealershipRules,
        private VehicleAvailabilityRuleRepository $vehicleRules,
        private AvailabilityExceptionRepository $exceptions,
        private AppointmentRepository $appointments,
        private AvailabilityCalculator $calculator,
    ) {
    }

    /** @return list<\DateTimeImmutable> */
    public function __invoke(?string $vehicleId, \DateTimeImmutable $date): array
    {
        $vehicle = $this->vehicles->findOrFail($vehicleId);
        $dealership = $this->dealerships->findOrFail($vehicle->dealershipId);
        $nextDay = $date->modify('+1 day');

        return $this->calculator->slotsFor(
            date: $date,
            dealershipWindows: array_map(static fn (DealershipAvailabilityRule $rule) => $rule->window, $this->dealershipRules->findByDealership($dealership->id)),
            vehicleWindows: array_map(static fn (VehicleAvailabilityRule $rule) => $rule->window, $this->vehicleRules->findByVehicle($vehicle->id)),
            exceptions: [
                ...$this->exceptions->findByDealershipInRange($dealership->id, $date, $date),
                ...$this->exceptions->findByVehicleInRange($vehicle->id, $date, $date),
            ],
            occupiedStarts: $this->appointments->findOccupiedStarts($vehicle->id, $date, $nextDay),
            durationMinutes: Appointment::DURATION_MINUTES,
        );
    }
}
