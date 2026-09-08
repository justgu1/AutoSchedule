<?php

declare(strict_types=1);

namespace App\Domain\Availability;

use App\Domain\Shared\Uuid;

final readonly class VehicleAvailabilityRule
{
    public function __construct(
        public string $id,
        public string $vehicleId,
        public WeeklyWindow $window,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function register(string $vehicleId, WeeklyWindow $window): self
    {
        $now = new \DateTimeImmutable();

        return new self(Uuid::v7(), $vehicleId, $window, $now, $now);
    }

    #[\NoDiscard]
    public function withWindow(WeeklyWindow $window): self
    {
        return clone($this, ['window' => $window, 'updatedAt' => new \DateTimeImmutable()]);
    }
}
