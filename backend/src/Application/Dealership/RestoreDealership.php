<?php

declare(strict_types=1);

namespace App\Application\Dealership;

use App\Application\Shared\ActorContext;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\Ports\AuditLogger;
use App\Domain\Dealership\Ports\DealershipRepository;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;

final readonly class RestoreDealership
{
    public function __construct(
        private DealershipFinder $finder,
        private DealershipRepository $dealerships,
        private AuditLogger $audit,
    ) {
    }

    public function __invoke(?string $identifier, ActorContext $context): void
    {
        $dealership = $this->finder->findOrFail($identifier);

        if (!$dealership->isEligibleForRestore()) {
            throw new DomainException('This dealership is not in the trash (or was already permanently deleted).', DomainErrorType::Conflict);
        }

        $this->dealerships->restore($dealership->id);
        $this->audit->record(AuditEvent::DealershipRestored, $context->actorId, 'Dealership', $dealership->id, [], $context->ipAddress, $context->userAgent);
    }
}
