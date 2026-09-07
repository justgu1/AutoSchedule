<?php

declare(strict_types=1);

namespace App\Application\Dealership;

use App\Application\Shared\ActorContext;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\Ports\AuditLogger;
use App\Domain\Dealership\Ports\DealershipRepository;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;

final readonly class RemoveDealershipPhoto
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

        if ($dealership->photoFileId === null) {
            throw new DomainException('This dealership has no photo.', DomainErrorType::NotFound);
        }

        $oldPhotoFileId = $dealership->photoFileId;
        $this->dealerships->update($dealership->withPhoto(null));
        $this->photos->delete($oldPhotoFileId);
        $this->audit->record($context->audits(AuditEvent::DealershipPhotoRemoved, $dealership->id));
    }
}
