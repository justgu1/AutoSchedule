<?php

declare(strict_types=1);

namespace App\Application\Vehicle;

use App\Application\Shared\ActorContext;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\Ports\AuditLogger;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Domain\Vehicle\Ports\VehicleRepository;

final readonly class RestoreVehicle
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

        if (!$vehicle->trash->allowsRestore()) {
            throw new DomainException('This vehicle is not in the trash (or was already permanently deleted).', DomainErrorType::Conflict);
        }

        $this->vehicles->restore($vehicle->id);
        $this->audit->record($context->audits(AuditEvent::VehicleRestored, $vehicle->id));
    }
}
