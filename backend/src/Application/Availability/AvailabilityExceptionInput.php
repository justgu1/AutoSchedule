<?php

declare(strict_types=1);

namespace App\Application\Availability;

use App\Application\Shared\ValidatedInput;

/** Reaproveitado por criar/atualizar exceção -- o parse de data e hora opcional mora uma vez só. */
final readonly class AvailabilityExceptionInput
{
    public static function date(ValidatedInput $data): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $data->string('date'));

        return $date instanceof \DateTimeImmutable ? $date : throw new \LogicException('Date was not validated as YYYY-MM-DD.');
    }

    public static function timeOrNull(ValidatedInput $data, string $field): ?\DateTimeImmutable
    {
        $value = $data->stringOrNull($field);

        return $value === null ? null : WeeklyWindowInput::time($value);
    }
}
