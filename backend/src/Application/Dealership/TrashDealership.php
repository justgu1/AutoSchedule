<?php

declare(strict_types=1);

namespace App\Application\Dealership;

use App\Application\Shared\ActorContext;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\Ports\AuditLogger;
use App\Domain\Dealership\Ports\DealershipRepository;

/** Reversível por `RestoreDealership` enquanto a purga agendada não passar. */
final readonly class TrashDealership
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

        $this->dealerships->trash($dealership->id);
        $this->audit->record(AuditEvent::DealershipTrashed, $context->actorId, 'Dealership', $dealership->id, [], $context->ipAddress, $context->userAgent);
    }
}
