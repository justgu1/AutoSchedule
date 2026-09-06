<?php

declare(strict_types=1);

namespace App\Domain\Dealerships;

use App\Domain\Support\Uuid;

final readonly class DealershipImage
{
    public function __construct(
        public string $id,
        public string $dealershipId,
        public string $fileId,
        public int $position,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function register(string $dealershipId, string $fileId, int $position): self
    {
        $now = new \DateTimeImmutable();

        return new self(Uuid::v7(), $dealershipId, $fileId, $position, $now, $now);
    }
}
