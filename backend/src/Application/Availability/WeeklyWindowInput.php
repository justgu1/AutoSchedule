<?php

declare(strict_types=1);

namespace App\Application\Availability;

use App\Application\Shared\ValidatedInput;
use App\Domain\Availability\WeeklyWindow;

/** Reaproveitado pelos quatro casos de uso de regra recorrente -- o parse de `HH:MM` mora uma vez só. */
final readonly class WeeklyWindowInput
{
    public static function fromValidated(ValidatedInput $data): WeeklyWindow
    {
        return new WeeklyWindow($data->int('weekday'), self::time($data->string('start_time')), self::time($data->string('end_time')));
    }

    public static function time(string $value): \DateTimeImmutable
    {
        $time = \DateTimeImmutable::createFromFormat('!H:i', $value);

        return $time instanceof \DateTimeImmutable ? $time : throw new \LogicException('Time was not validated as HH:MM.');
    }
}
