<?php

declare(strict_types=1);

namespace App\Application\Availability;

use App\Application\Dealership\DealershipFinder;
use App\Domain\Availability\DealershipAvailabilityRule;
use App\Domain\Availability\Ports\DealershipAvailabilityRuleRepository;

final readonly class ListDealershipAvailabilityRules
{
    public function __construct(
        private DealershipFinder $dealerships,
        private DealershipAvailabilityRuleRepository $rules,
    ) {
    }

    /** @return list<DealershipAvailabilityRule> */
    public function __invoke(?string $dealershipId): array
    {
        $dealership = $this->dealerships->findOrFail($dealershipId);

        return $this->rules->findByDealership($dealership->id);
    }
}
