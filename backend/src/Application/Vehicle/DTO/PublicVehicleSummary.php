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
        public ?int $modelYear,
        public string $price,
        public ?int $mileageKm,
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
            modelYear: $vehicle->modelYear,
            price: $vehicle->price->toDecimal(),
            mileageKm: $vehicle->mileageKm,
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
            'model_year' => $this->modelYear,
            'price' => $this->price,
            'mileage_km' => $this->mileageKm,
            'photo_url' => $this->photoUrl,
        ];
    }
}
