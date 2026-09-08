<?php

declare(strict_types=1);

namespace App\Application\Vehicle\DTO;

use App\Domain\Dealership\Dealership;
use App\Domain\Shared\Money;
use App\Domain\Vehicle\Vehicle;

/** Sem `dealership_id`/`status` -- quem visita não gerencia nada. Concessionária aninhada e enxuta. */
final readonly class PublicVehicleProfile
{
    /** @param list<array{id: string, position: int, url: string}> $images */
    public function __construct(
        public string $id,
        public string $brand,
        public string $model,
        public ?string $version,
        public ?int $year,
        public Money $price,
        public ?string $description,
        public array $images,
        public string $dealershipSlug,
        public string $dealershipName,
        public string $dealershipCity,
        public string $dealershipState,
    ) {
    }

    /** @param list<array{id: string, position: int, url: string}> $images */
    public static function fromVehicle(Vehicle $vehicle, array $images, Dealership $dealership): self
    {
        return new self(
            id: $vehicle->id,
            brand: $vehicle->brand,
            model: $vehicle->model,
            version: $vehicle->version,
            year: $vehicle->year,
            price: $vehicle->price,
            description: $vehicle->description,
            images: $images,
            dealershipSlug: $dealership->slug,
            dealershipName: $dealership->name,
            dealershipCity: $dealership->address->city,
            dealershipState: $dealership->address->state->value,
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
            'price' => $this->price->toDecimal(),
            'description' => $this->description,
            'images' => $this->images,
            'dealership' => [
                'slug' => $this->dealershipSlug,
                'name' => $this->dealershipName,
                'city' => $this->dealershipCity,
                'state' => $this->dealershipState,
            ],
        ];
    }
}
