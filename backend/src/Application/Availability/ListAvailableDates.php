<?php

declare(strict_types=1);

namespace App\Application\Availability;

use App\Application\Dealership\DealershipFinder;
use App\Application\Vehicle\VehicleFinder;
use App\Domain\Appointment\Appointment;
use App\Domain\Appointment\Ports\AppointmentRepository;
use App\Domain\Availability\AvailabilityCalculator;
use App\Domain\Availability\AvailabilityException;
use App\Domain\Availability\DealershipAvailabilityRule;
use App\Domain\Availability\Ports\AvailabilityExceptionRepository;
use App\Domain\Availability\Ports\DealershipAvailabilityRuleRepository;
use App\Domain\Availability\Ports\VehicleAvailabilityRuleRepository;
use App\Domain\Availability\VehicleAvailabilityRule;

/** Varre um intervalo de datas e devolve só as que têm pelo menos um slot livre. */
final readonly class ListAvailableDates
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
    public function __invoke(?string $vehicleId, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $vehicle = $this->vehicles->findOrFail($vehicleId);
        $dealership = $this->dealerships->findOrFail($vehicle->dealershipId);

        $dealershipWindows = array_map(static fn (DealershipAvailabilityRule $rule) => $rule->window, $this->dealershipRules->findByDealership($dealership->id));
        $vehicleWindows = array_map(static fn (VehicleAvailabilityRule $rule) => $rule->window, $this->vehicleRules->findByVehicle($vehicle->id));

        $exceptions = [
            ...$this->exceptions->findByDealershipInRange($dealership->id, $from, $to),
            ...$this->exceptions->findByVehicleInRange($vehicle->id, $from, $to),
        ];
        $occupied = $this->appointments->findOccupiedStarts($vehicle->id, $from, $to->modify('+1 day'));

        $dates = [];
        $now = new \DateTimeImmutable();

        for ($cursor = $from; $cursor < $to; $cursor = $cursor->modify('+1 day')) {
            $slots = $this->calculator->slotsFor(
                date: $cursor,
                dealershipWindows: $dealershipWindows,
                vehicleWindows: $vehicleWindows,
                exceptions: $this->onDate($exceptions, $cursor),
                occupiedStarts: $this->onDate($occupied, $cursor),
                durationMinutes: Appointment::DURATION_MINUTES,
                notBefore: $now,
            );

            if ($slots !== []) {
                $dates[] = $cursor;
            }
        }

        return $dates;
    }

    /**
     * @template T of AvailabilityException|\DateTimeImmutable
     * @param list<T> $items
     * @return list<T>
     */
    private function onDate(array $items, \DateTimeImmutable $date): array
    {
        return array_values(array_filter(
            $items,
            static fn (AvailabilityException|\DateTimeImmutable $item): bool => ($item instanceof AvailabilityException ? $item->date : $item)->format('Y-m-d') === $date->format('Y-m-d'),
        ));
    }
}
