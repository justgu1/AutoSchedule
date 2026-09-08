<?php

declare(strict_types=1);

namespace App\Application\Dealership;

use App\Application\Ports\Transaction;
use App\Application\Shared\ActorContext;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\Ports\AuditLogger;
use App\Domain\Dealership\Ports\DealershipRepository;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Domain\Vehicle\Ports\VehicleRepository;

final readonly class RestoreDealership
{
    public function __construct(
        private DealershipFinder $finder,
        private DealershipRepository $dealerships,
        private VehicleRepository $vehicles,
        private AuditLogger $audit,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(?string $identifier, ActorContext $context): void
    {
        $dealership = $this->finder->findOrFail($identifier);

        if (!$dealership->trash->allowsRestore()) {
            throw new DomainException('This dealership is not in the trash (or was already permanently deleted).', DomainErrorType::Conflict);
        }

        $this->transaction->run(function () use ($dealership): void {
            $this->dealerships->restore($dealership->id);
            $this->vehicles->restoreAutoTrashedInDealership($dealership->id);
        });

        $this->audit->record($context->audits(AuditEvent::DealershipRestored, $dealership->id));
    }
}
