<?php

declare(strict_types=1);

namespace App\Domain\Availability;

use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Domain\Shared\Uuid;

/**
 * Escopo é concessionária OU veículo, nunca os dois nem nenhum. `isAvailable=false` sem hora bloqueia
 * o dia inteiro; com hora, subtrai só aquele intervalo; `isAvailable=true` substitui a janela do dia.
 */
final readonly class AvailabilityException
{
    public function __construct(
        public string $id,
        public ?string $dealershipId,
        public ?string $vehicleId,
        public \DateTimeImmutable $date,
        public ?\DateTimeImmutable $startTime,
        public ?\DateTimeImmutable $endTime,
        public bool $isAvailable,
        public ?string $reason,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
    ) {
        if (($dealershipId !== null) === ($vehicleId !== null)) {
            throw new DomainException('Invalid data.', DomainErrorType::Validation, ['dealership_id' => 'Exactly one of dealership_id or vehicle_id must be set.']);
        }

        if ($startTime instanceof \DateTimeImmutable && $endTime instanceof \DateTimeImmutable && $startTime >= $endTime) {
            throw new DomainException('Invalid data.', DomainErrorType::Validation, ['end_time' => 'The end_time field must be after start_time.']);
        }

        if ($isAvailable && (!$startTime instanceof \DateTimeImmutable || !$endTime instanceof \DateTimeImmutable)) {
            throw new DomainException('Invalid data.', DomainErrorType::Validation, ['start_time' => 'Opening a special window requires start_time and end_time.']);
        }
    }

    public static function register(
        ?string $dealershipId,
        ?string $vehicleId,
        \DateTimeImmutable $date,
        ?\DateTimeImmutable $startTime,
        ?\DateTimeImmutable $endTime,
        bool $isAvailable,
        ?string $reason,
    ): self {
        $now = new \DateTimeImmutable();

        return new self(Uuid::v7(), $dealershipId, $vehicleId, $date, $startTime, $endTime, $isAvailable, $reason, $now, $now);
    }

    #[\NoDiscard]
    public function withDetails(
        \DateTimeImmutable $date,
        ?\DateTimeImmutable $startTime,
        ?\DateTimeImmutable $endTime,
        bool $isAvailable,
        ?string $reason,
    ): self {
        return clone($this, [
            'date' => $date,
            'startTime' => $startTime,
            'endTime' => $endTime,
            'isAvailable' => $isAvailable,
            'reason' => $reason,
            'updatedAt' => new \DateTimeImmutable(),
        ]);
    }
}
