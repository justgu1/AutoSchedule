<?php

declare(strict_types=1);

namespace App\Application\Vehicle\DTO;

use App\Domain\Vehicle\Vehicle;

/** Card do catálogo público -- sem `dealership_id` nem `status` de gestão, o visitante não gerencia nada. */
final readonly class PublicVehicleSummary
{
    public function __construct(
        public string $id,
        public string $brand,
        public string $model,
        public ?string $version,
        public ?int $year,
        public string $price,
        public ?string $photoUrl,
    ) {
    }

    public static function fromVehicle(Vehicle $vehicle, ?string $photoUrl): self
    {
        return new self(
            id: $vehicle->id,
            brand: $vehicle->brand,
            model: $vehicle->model,
            version: $vehicle->version,
            year: $vehicle->year,
            price: $vehicle->price->toDecimal(),
            photoUrl: $photoUrl,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'brand' => $this->brand,
            'model' => $this->model,
            'version' => $this->version,
            'year' => $this->year,
            'price' => $this->price,
            'photo_url' => $this->photoUrl,
        ];
    }
}
