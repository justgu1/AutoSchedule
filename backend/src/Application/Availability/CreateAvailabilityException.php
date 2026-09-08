<?php

declare(strict_types=1);

namespace App\Application\Availability;

use App\Application\Dealership\DealershipFinder;
use App\Application\Shared\ActorContext;
use App\Application\Shared\ValidatedInput;
use App\Application\Vehicle\VehicleFinder;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\Ports\AuditLogger;
use App\Domain\Availability\AvailabilityException;
use App\Domain\Availability\Ports\AvailabilityExceptionRepository;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;

final readonly class CreateAvailabilityException
{
    public function __construct(
        private DealershipFinder $dealerships,
        private VehicleFinder $vehicles,
        private AvailabilityExceptionRepository $exceptions,
        private AuditLogger $audit,
    ) {
    }

    public function __invoke(ValidatedInput $data, ActorContext $context): AvailabilityException
    {
        [$dealershipId, $vehicleId] = $this->resolveScope($data);

        $exception = AvailabilityException::register(
            dealershipId: $dealershipId,
            vehicleId: $vehicleId,
            date: AvailabilityExceptionInput::date($data),
            startTime: AvailabilityExceptionInput::timeOrNull($data, 'start_time'),
            endTime: AvailabilityExceptionInput::timeOrNull($data, 'end_time'),
            isAvailable: $data->boolOr('is_available', false),
            reason: $data->stringOrNull('reason'),
        );

        $this->exceptions->insert($exception);
        $this->audit->record($context->audits(AuditEvent::AvailabilityCreated, $exception->id));

        return $exception;
    }

    /** @return array{0: ?string, 1: ?string} */
    private function resolveScope(ValidatedInput $data): array
    {
        $dealershipId = $data->stringOrNull('dealership_id');
        $vehicleId = $data->stringOrNull('vehicle_id');

        if ($dealershipId !== null) {
            return [$this->dealerships->findOrFail($dealershipId)->id, null];
        }

        if ($vehicleId !== null) {
            return [null, $this->vehicles->findOrFail($vehicleId)->id];
        }

        throw new DomainException('Invalid data.', DomainErrorType::Validation, ['dealership_id' => 'Exactly one of dealership_id or vehicle_id must be set.']);
    }
}
