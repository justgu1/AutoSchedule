<?php

declare(strict_types=1);

namespace App\Domain\Vehicle;

use App\Domain\Shared\Money;
use App\Domain\Shared\Trashable;
use App\Domain\Shared\TrashState;
use App\Domain\Shared\Uuid;

final readonly class Vehicle implements Trashable
{
    public function __construct(
        public string $id,
        public string $dealershipId,
        public string $brand,
        public string $model,
        public ?string $version,
        public ?int $manufactureYear,
        public ?int $modelYear,
        public Money $price,
        public ?string $description,
        public ?int $mileageKm,
        public ?Transmission $transmission,
        public ?BodyType $bodyType,
        public ?FuelType $fuelType,
        public ?string $color,
        public ?int $plateEndDigit,
        public bool $acceptsTrade,
        public bool $ipvaPaid,
        public bool $licensed,
        public TrashState $trash,
        public bool $trashedByDealershipTrash,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function register(
        string $dealershipId,
        string $brand,
        string $model,
        ?string $version,
        ?int $manufactureYear,
        ?int $modelYear,
        Money $price,
        ?string $description = null,
        ?int $mileageKm = null,
        ?Transmission $transmission = null,
        ?BodyType $bodyType = null,
        ?FuelType $fuelType = null,
        ?string $color = null,
        ?int $plateEndDigit = null,
        bool $acceptsTrade = false,
        bool $ipvaPaid = false,
        bool $licensed = false,
    ): self {
        $now = new \DateTimeImmutable();

        return new self(
            id: Uuid::v7(),
            dealershipId: $dealershipId,
            brand: $brand,
            model: $model,
            version: $version,
            manufactureYear: $manufactureYear,
            modelYear: $modelYear,
            price: $price,
            description: $description,
            mileageKm: $mileageKm,
            transmission: $transmission,
            bodyType: $bodyType,
            fuelType: $fuelType,
            color: $color,
            plateEndDigit: $plateEndDigit,
            acceptsTrade: $acceptsTrade,
            ipvaPaid: $ipvaPaid,
            licensed: $licensed,
            trash: new TrashState(),
            trashedByDealershipTrash: false,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    #[\NoDiscard]
    public function withDetails(
        string $brand,
        string $model,
        ?string $version,
        ?int $manufactureYear,
        ?int $modelYear,
        Money $price,
        ?string $description,
        ?int $mileageKm,
        ?Transmission $transmission,
        ?BodyType $bodyType,
        ?FuelType $fuelType,
        ?string $color,
        ?int $plateEndDigit,
        bool $acceptsTrade,
        bool $ipvaPaid,
        bool $licensed,
    ): self {
        return clone($this, [
            'brand' => $brand,
            'model' => $model,
            'version' => $version,
            'manufactureYear' => $manufactureYear,
            'modelYear' => $modelYear,
            'price' => $price,
            'description' => $description,
            'mileageKm' => $mileageKm,
            'transmission' => $transmission,
            'bodyType' => $bodyType,
            'fuelType' => $fuelType,
            'color' => $color,
            'plateEndDigit' => $plateEndDigit,
            'acceptsTrade' => $acceptsTrade,
            'ipvaPaid' => $ipvaPaid,
            'licensed' => $licensed,
            'updatedAt' => new \DateTimeImmutable(),
        ]);
    }

    /** Trocar de concessionária é trocar de dono, já que a propriedade é resolvida por ela. */
    #[\NoDiscard]
    public function movedTo(string $dealershipId): self
    {
        return clone($this, ['dealershipId' => $dealershipId, 'updatedAt' => new \DateTimeImmutable()]);
    }

    /** Veículo não tem dado pessoal pra escrubar, e marca/modelo seguem sendo o que o histórico de agendamento exibe. */
    #[\NoDiscard]
    public function anonymized(): static
    {
        return clone($this, ['trash' => $this->trash->anonymized()]);
    }
}
