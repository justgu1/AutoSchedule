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
        public ?int $year,
        public Money $price,
        public ?string $description,
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
        ?int $year,
        Money $price,
        ?string $description = null,
    ): self {
        $now = new \DateTimeImmutable();

        return new self(
            id: Uuid::v7(),
            dealershipId: $dealershipId,
            brand: $brand,
            model: $model,
            version: $version,
            year: $year,
            price: $price,
            description: $description,
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
        ?int $year,
        Money $price,
        ?string $description,
    ): self {
        return clone($this, [
            'brand' => $brand,
            'model' => $model,
            'version' => $version,
            'year' => $year,
            'price' => $price,
            'description' => $description,
            'updatedAt' => new \DateTimeImmutable(),
        ]);
    }

    /** Veículo não tem dado pessoal pra escrubar, e marca/modelo seguem sendo o que o histórico de agendamento exibe. */
    #[\NoDiscard]
    public function anonymized(): static
    {
        return clone($this, ['trash' => $this->trash->anonymized()]);
    }
}
