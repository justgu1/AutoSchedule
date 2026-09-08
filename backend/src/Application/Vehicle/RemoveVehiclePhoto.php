<?php

declare(strict_types=1);

namespace App\Application\Vehicle;

use App\Application\Ports\Transaction;
use App\Application\Shared\ActorContext;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\Ports\AuditLogger;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Domain\Vehicle\Ports\VehicleImageRepository;
use App\Domain\Vehicle\VehicleImage;

final readonly class RemoveVehiclePhoto
{
    public function __construct(
        private VehicleFinder $finder,
        private VehicleImageRepository $images,
        private VehicleGallery $gallery,
        private AuditLogger $audit,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(?string $vehicleId, ?string $imageId, ActorContext $context): void
    {
        $vehicle = $this->finder->findOrFail($vehicleId);
        $image = $imageId === null ? null : $this->images->findById($imageId);

        if (!$image instanceof VehicleImage || $image->vehicleId !== $vehicle->id) {
            throw new DomainException('Image not found.', DomainErrorType::NotFound);
        }

        // Soltar a linha e o arquivo é escrita dependente: sem transação, uma falha deixa o objeto órfão no storage.
        $this->transaction->run(function () use ($image): void {
            $this->gallery->detach($image);
        });

        $this->audit->record($context->audits(AuditEvent::VehicleImageRemoved, $vehicle->id, ['image_id' => $image->id]));
    }
}
