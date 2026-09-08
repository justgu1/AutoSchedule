<?php

declare(strict_types=1);

namespace App\Application\Availability;

use App\Application\Dealership\DealershipFinder;
use App\Application\Shared\ActorContext;
use App\Application\Shared\ValidatedInput;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\Ports\AuditLogger;
use App\Domain\Availability\DealershipAvailabilityRule;
use App\Domain\Availability\Ports\DealershipAvailabilityRuleRepository;

final readonly class CreateDealershipAvailabilityRule
{
    public function __construct(
        private DealershipFinder $dealerships,
        private DealershipAvailabilityRuleRepository $rules,
        private AuditLogger $audit,
    ) {
    }

    public function __invoke(?string $dealershipId, ValidatedInput $data, ActorContext $context): DealershipAvailabilityRule
    {
        $dealership = $this->dealerships->findOrFail($dealershipId);
        $rule = DealershipAvailabilityRule::register($dealership->id, WeeklyWindowInput::fromValidated($data));

        $this->rules->insert($rule);
        $this->audit->record($context->audits(AuditEvent::AvailabilityCreated, $rule->id));

        return $rule;
    }
}
