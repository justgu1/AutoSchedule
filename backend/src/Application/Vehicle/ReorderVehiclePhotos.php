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

/** A ordem chega inteira, não um passo por vez: é a única forma de validar que a permutação está completa. */
final readonly class ReorderVehiclePhotos
{
    public function __construct(
        private VehicleFinder $finder,
        private VehicleImageRepository $images,
        private AuditLogger $audit,
        private Transaction $transaction,
    ) {
    }

    /** @param list<string> $imageIdsInOrder */
    public function __invoke(?string $vehicleId, array $imageIdsInOrder, ActorContext $context): void
    {
        $vehicle = $this->finder->findOrFail($vehicleId);
        $current = array_map(static fn (VehicleImage $image): string => $image->id, $this->images->findByVehicle($vehicle->id));

        if (array_diff($current, $imageIdsInOrder) !== [] || count($current) !== count($imageIdsInOrder)) {
            throw new DomainException('Invalid data.', DomainErrorType::Validation, [
                'order' => 'The order must list every image of this vehicle exactly once.',
            ]);
        }

        $this->transaction->run(function () use ($vehicle, $imageIdsInOrder): void {
            $this->images->reorder($vehicle->id, $imageIdsInOrder);
        });

        $this->audit->record($context->audits(AuditEvent::VehicleImagesReordered, $vehicle->id));
    }
}
