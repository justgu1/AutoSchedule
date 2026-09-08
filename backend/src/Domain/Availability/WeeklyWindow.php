<?php

declare(strict_types=1);

namespace App\Domain\Availability;

use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;

/** Único lugar que valida o invariante das duas tabelas de regra recorrente -- elas reaproveitam, não reimplementam. */
final readonly class WeeklyWindow
{
    public function __construct(
        public int $weekday,
        public \DateTimeImmutable $startTime,
        public \DateTimeImmutable $endTime,
    ) {
        if ($weekday < 0 || $weekday > 6) {
            throw new DomainException('Invalid data.', DomainErrorType::Validation, ['weekday' => 'The weekday field must be between 0 and 6.']);
        }

        if ($startTime >= $endTime) {
            throw new DomainException('Invalid data.', DomainErrorType::Validation, ['end_time' => 'The end_time field must be after start_time.']);
        }
    }
}
