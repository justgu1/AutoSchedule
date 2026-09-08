<?php

declare(strict_types=1);

namespace App\Domain\Vehicle;

use App\Domain\Shared\Uuid;

/** A capa é a posição 0 -- não existe coluna de destaque, que seria uma segunda verdade a sincronizar. */
final readonly class VehicleImage
{
    public function __construct(
        public string $id,
        public string $vehicleId,
        public string $fileId,
        public int $position,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function register(string $vehicleId, string $fileId, int $position): self
    {
        $now = new \DateTimeImmutable();

        return new self(
            id: Uuid::v7(),
            vehicleId: $vehicleId,
            fileId: $fileId,
            position: $position,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    #[\NoDiscard]
    public function movedTo(int $position): self
    {
        return clone($this, ['position' => $position, 'updatedAt' => new \DateTimeImmutable()]);
    }
}
