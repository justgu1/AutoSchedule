<?php

declare(strict_types=1);

namespace App\Application\Dealership;

use App\Application\Ports\Transaction;
use App\Application\Shared\ActorContext;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\Ports\AuditLogger;
use App\Domain\Dealership\Ports\DealershipRepository;
use App\Domain\Vehicle\Ports\VehicleRepository;

/** Reversível por `RestoreDealership` enquanto a purga agendada não passar. */
final readonly class TrashDealership
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

        $this->transaction->run(function () use ($dealership): void {
            $this->dealerships->trash($dealership->id);
            // Marcado como cascata pra o restore devolver só o que caiu por causa dela, não o que o seller já tinha arquivado.
            $this->vehicles->trashAllInDealership($dealership->id);
        });

        $this->audit->record($context->audits(AuditEvent::DealershipTrashed, $dealership->id));
    }
}
