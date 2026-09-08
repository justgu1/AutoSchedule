<?php

declare(strict_types=1);

namespace App\Application\Availability;

use App\Application\Shared\ActorContext;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\Ports\AuditLogger;
use App\Domain\Availability\Ports\AvailabilityExceptionRepository;

final readonly class DeleteAvailabilityException
{
    public function __construct(
        private AvailabilityExceptionFinder $finder,
        private AvailabilityExceptionRepository $exceptions,
        private AuditLogger $audit,
    ) {
    }

    public function __invoke(?string $id, ActorContext $context): void
    {
        $exception = $this->finder->findOrFail($id);

        $this->exceptions->delete($exception->id);
        $this->audit->record($context->audits(AuditEvent::AvailabilityDeleted, $exception->id));
    }
}
