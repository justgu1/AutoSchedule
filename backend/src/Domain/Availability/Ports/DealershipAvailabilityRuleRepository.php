<?php

declare(strict_types=1);

namespace App\Domain\Availability\Ports;

use App\Domain\Availability\DealershipAvailabilityRule;

interface DealershipAvailabilityRuleRepository
{
    public function findById(string $id): ?DealershipAvailabilityRule;

    public function insert(DealershipAvailabilityRule $rule): void;

    public function update(DealershipAvailabilityRule $rule): void;

    public function delete(string $id): void;

    /** @return list<DealershipAvailabilityRule> */
    public function findByDealership(string $dealershipId): array;
}
