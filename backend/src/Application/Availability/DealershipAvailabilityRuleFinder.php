<?php

declare(strict_types=1);

namespace App\Application\Availability;

use App\Domain\Availability\DealershipAvailabilityRule;
use App\Domain\Availability\Ports\DealershipAvailabilityRuleRepository;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;

/** "Não é sua" e "não existe" viram o mesmo 404 -- o RLS (delegado pra `dealerships`) já esconde a linha alheia. */
final readonly class DealershipAvailabilityRuleFinder
{
    public function __construct(private DealershipAvailabilityRuleRepository $rules)
    {
    }

    public function findOrFail(?string $id): DealershipAvailabilityRule
    {
        $rule = $id !== null ? $this->rules->findById($id) : null;

        if (!$rule instanceof DealershipAvailabilityRule) {
            throw new DomainException('Availability rule not found.', DomainErrorType::NotFound);
        }

        return $rule;
    }
}
