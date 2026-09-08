<?php

declare(strict_types=1);

namespace App\Application\Availability;

use App\Application\Shared\ActorContext;
use App\Application\Shared\ValidatedInput;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\Ports\AuditLogger;
use App\Domain\Availability\Ports\VehicleAvailabilityRuleRepository;
use App\Domain\Availability\VehicleAvailabilityRule;

final readonly class UpdateVehicleAvailabilityRule
{
    public function __construct(
        private VehicleAvailabilityRuleFinder $finder,
        private VehicleAvailabilityRuleRepository $rules,
        private AuditLogger $audit,
    ) {
    }

    public function __invoke(?string $id, ValidatedInput $data, ActorContext $context): VehicleAvailabilityRule
    {
        $updated = $this->finder->findOrFail($id)->withWindow(WeeklyWindowInput::fromValidated($data));

        $this->rules->update($updated);
        $this->audit->record($context->audits(AuditEvent::AvailabilityUpdated, $updated->id));

        return $updated;
    }
}
