<?php

declare(strict_types=1);

namespace App\Application\Availability;

use App\Application\Shared\ActorContext;
use App\Application\Shared\ValidatedInput;
use App\Application\Vehicle\VehicleFinder;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\Ports\AuditLogger;
use App\Domain\Availability\Ports\VehicleAvailabilityRuleRepository;
use App\Domain\Availability\VehicleAvailabilityRule;

final readonly class CreateVehicleAvailabilityRule
{
    public function __construct(
        private VehicleFinder $vehicles,
        private VehicleAvailabilityRuleRepository $rules,
        private AuditLogger $audit,
    ) {
    }

    public function __invoke(?string $vehicleId, ValidatedInput $data, ActorContext $context): VehicleAvailabilityRule
    {
        $vehicle = $this->vehicles->findOrFail($vehicleId);
        $rule = VehicleAvailabilityRule::register($vehicle->id, WeeklyWindowInput::fromValidated($data));

        $this->rules->insert($rule);
        $this->audit->record($context->audits(AuditEvent::AvailabilityCreated, $rule->id));

        return $rule;
    }
}
