<?php

declare(strict_types=1);

namespace App\Application\Vehicle;

use App\Application\Ports\Transaction;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Domain\Vehicle\Amenity;
use App\Domain\Vehicle\Ports\VehicleAmenityCatalog;
use App\Domain\Vehicle\Ports\VehicleAmenityLinkRepository;

final readonly class VehicleAmenities
{
    public function __construct(
        private VehicleAmenityCatalog $catalog,
        private VehicleAmenityLinkRepository $links,
        private Transaction $transaction,
    ) {
    }

    /** @return list<Amenity> */
    public function catalog(): array
    {
        return $this->catalog->all();
    }

    /** @return list<array{id: string, code: string, label: string}> na ordem do catálogo, não da inserção */
    public function linksFor(string $vehicleId): array
    {
        $linkedIds = $this->links->amenityIdsFor($vehicleId);

        return array_values(array_filter(array_map(
            static fn (Amenity $a): ?array => in_array($a->id, $linkedIds, true)
                ? ['id' => $a->id, 'code' => $a->code, 'label' => $a->label]
                : null,
            $this->catalog->all(),
        )));
    }

    /** @param list<string> $amenityIds */
    public function replace(string $vehicleId, array $amenityIds): void
    {
        $valid = $this->catalog->existingIds($amenityIds);

        if (count($valid) !== count(array_unique($amenityIds))) {
            throw new DomainException('Invalid data.', DomainErrorType::Validation, ['amenity_ids' => 'One or more amenity ids do not exist.']);
        }

        $this->transaction->run(fn () => $this->links->replace($vehicleId, $valid));
    }
}
