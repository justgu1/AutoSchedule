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

/** Antecipa o que a purga agendada faria, a pedido de quem é dono. */
final readonly class PurgeDealership
{
    public function __construct(
        private DealershipFinder $finder,
        private DealershipRepository $dealerships,
        private DealershipPhotos $photos,
        private VehicleRepository $vehicles,
        private AuditLogger $audit,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(?string $identifier, ActorContext $context): void
    {
        $dealership = $this->finder->findOrFail($identifier);

        if (!$dealership->trash->isTrashed()) {
            throw new DomainException('This dealership is not in the trash.', DomainErrorType::Conflict);
        }

        $this->transaction->run(function () use ($dealership): void {
            $oldPhotoFileId = $dealership->photoFileId;
            $this->dealerships->update($dealership->anonymized());
            $this->vehicles->trashAllInDealership($dealership->id);
            $this->photos->delete($oldPhotoFileId);
        });

        $this->audit->record($context->audits(AuditEvent::DealershipPurged, $dealership->id));
    }
}
