<?php

declare(strict_types=1);

namespace App\Application\Vehicle;

use App\Application\Ports\Transaction;
use App\Application\Shared\ActorContext;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\Ports\AuditLogger;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Domain\Vehicle\Ports\VehicleRepository;

/**
 * Antecipa o que a purga agendada faria, a pedido de quem é dono -- e limpa a galeria junto,
 * que a rotina genérica não tem como fazer.
 */
final readonly class PurgeVehicle
{
    public function __construct(
        private VehicleFinder $finder,
        private VehicleRepository $vehicles,
        private VehicleGallery $gallery,
        private AuditLogger $audit,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(?string $id, ActorContext $context): void
    {
        $vehicle = $this->finder->findOrFail($id);

        if (!$vehicle->trash->isTrashed()) {
            throw new DomainException('This vehicle is not in the trash.', DomainErrorType::Conflict);
        }

        $this->transaction->run(function () use ($vehicle): void {
            $this->gallery->detachAll($vehicle->id);
            $this->vehicles->update($vehicle->anonymized());
        });

        $this->audit->record($context->audits(AuditEvent::VehiclePurged, $vehicle->id));
    }
}
