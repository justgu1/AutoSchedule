<?php

declare(strict_types=1);

namespace App\Application\Availability;

use App\Application\Shared\ActorContext;
use App\Application\Shared\ValidatedInput;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\Ports\AuditLogger;
use App\Domain\Availability\AvailabilityException;
use App\Domain\Availability\Ports\AvailabilityExceptionRepository;

/** Escopo (dealership_id/vehicle_id) não muda numa atualização -- só quem cria decide onde a exceção mora. */
final readonly class UpdateAvailabilityException
{
    public function __construct(
        private AvailabilityExceptionFinder $finder,
        private AvailabilityExceptionRepository $exceptions,
        private AuditLogger $audit,
    ) {
    }

    public function __invoke(?string $id, ValidatedInput $data, ActorContext $context): AvailabilityException
    {
        $updated = $this->finder->findOrFail($id)->withDetails(
            date: AvailabilityExceptionInput::date($data),
            startTime: AvailabilityExceptionInput::timeOrNull($data, 'start_time'),
            endTime: AvailabilityExceptionInput::timeOrNull($data, 'end_time'),
            isAvailable: $data->boolOr('is_available', false),
            reason: $data->stringOrNull('reason'),
        );

        $this->exceptions->update($updated);
        $this->audit->record($context->audits(AuditEvent::AvailabilityUpdated, $updated->id));

        return $updated;
    }
}
