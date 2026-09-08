<?php

declare(strict_types=1);

namespace App\Application\Availability;

use App\Application\Shared\ActorContext;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\Ports\AuditLogger;
use App\Domain\Availability\Ports\DealershipAvailabilityRuleRepository;

final readonly class DeleteDealershipAvailabilityRule
{
    public function __construct(
        private DealershipAvailabilityRuleFinder $finder,
        private DealershipAvailabilityRuleRepository $rules,
        private AuditLogger $audit,
    ) {
    }

    public function __invoke(?string $id, ActorContext $context): void
    {
        $rule = $this->finder->findOrFail($id);

        $this->rules->delete($rule->id);
        $this->audit->record($context->audits(AuditEvent::AvailabilityDeleted, $rule->id));
    }
}
