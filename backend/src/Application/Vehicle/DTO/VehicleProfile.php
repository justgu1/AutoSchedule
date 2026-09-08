<?php

declare(strict_types=1);

namespace App\Application\Vehicle\DTO;

use App\Domain\Shared\Money;
use App\Domain\Vehicle\Vehicle;

final readonly class VehicleProfile
{
    public function __construct(
        public string $id,
        public string $dealershipId,
        public string $brand,
        public string $model,
        public ?string $version,
        public ?int $year,
        public Money $price,
        public ?string $description,
        public string $status,
    ) {
    }

    public static function fromVehicle(Vehicle $vehicle): self
    {
        return new self(
            id: $vehicle->id,
            dealershipId: $vehicle->dealershipId,
            brand: $vehicle->brand,
            model: $vehicle->model,
            version: $vehicle->version,
            year: $vehicle->year,
            price: $vehicle->price,
            description: $vehicle->description,
            status: $vehicle->trash->status->value,
        );
    }

    /**
     * `price` sai como string: número em JSON vira IEEE754 no cliente, que é o mesmo centavo
     * perdido que `numeric(12,2)` evita no banco.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'dealership_id' => $this->dealershipId,
            'brand' => $this->brand,
            'model' => $this->model,
            'version' => $this->version,
            'year' => $this->year,
            'price' => $this->price->toDecimal(),
            'description' => $this->description,
            'status' => $this->status,
        ];
    }
}
