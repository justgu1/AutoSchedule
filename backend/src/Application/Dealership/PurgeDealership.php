<?php

declare(strict_types=1);

namespace App\Application\Dealership;

use App\Application\Shared\ActorContext;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\Ports\AuditLogger;
use App\Domain\Dealership\Ports\DealershipRepository;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;

/** Antecipa o que a purga agendada faria, a pedido de quem é dono. */
final readonly class PurgeDealership
{
    public function __construct(
        private DealershipFinder $finder,
        private DealershipRepository $dealerships,
        private DealershipPhotos $photos,
        private AuditLogger $audit,
    ) {
    }

    public function __invoke(?string $identifier, ActorContext $context): void
    {
        $dealership = $this->finder->findOrFail($identifier);

        if (!$dealership->trash->isTrashed()) {
            throw new DomainException('This dealership is not in the trash.', DomainErrorType::Conflict);
        }

        $oldPhotoFileId = $dealership->photoFileId;
        $this->dealerships->update($dealership->anonymized());
        $this->photos->delete($oldPhotoFileId);
        $this->audit->record(AuditEvent::DealershipPurged, $context->actorId, 'Dealership', $dealership->id, [], $context->ipAddress, $context->userAgent);
    }
}
