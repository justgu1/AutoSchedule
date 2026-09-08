<?php

declare(strict_types=1);

namespace App\Application\Vehicle;

use App\Application\Shared\ActorContext;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\Ports\AuditLogger;
use App\Domain\Vehicle\Ports\VehicleRepository;

/** Reversível por `RestoreVehicle` enquanto a purga agendada não passar. */
final readonly class TrashVehicle
{
    public function __construct(
        private VehicleFinder $finder,
        private VehicleRepository $vehicles,
        private AuditLogger $audit,
    ) {
    }

    public function __invoke(?string $id, ActorContext $context): void
    {
        $vehicle = $this->finder->findOrFail($id);

        $this->vehicles->trash($vehicle->id);
        $this->audit->record($context->audits(AuditEvent::VehicleTrashed, $vehicle->id));
    }
}
