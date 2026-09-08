<?php

declare(strict_types=1);

namespace App\Application\Availability;

use App\Domain\Availability\Ports\VehicleAvailabilityRuleRepository;
use App\Domain\Availability\VehicleAvailabilityRule;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;

/** "Não é sua" e "não existe" viram o mesmo 404 -- o RLS (delegado pra `vehicles`) já esconde a linha alheia. */
final readonly class VehicleAvailabilityRuleFinder
{
    public function __construct(private VehicleAvailabilityRuleRepository $rules)
    {
    }

    public function findOrFail(?string $id): VehicleAvailabilityRule
    {
        $rule = $id !== null ? $this->rules->findById($id) : null;

        if (!$rule instanceof VehicleAvailabilityRule) {
            throw new DomainException('Availability rule not found.', DomainErrorType::NotFound);
        }

        return $rule;
    }
}
