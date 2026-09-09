<?php

declare(strict_types=1);

namespace App\Domain\Availability;

/**
 * Puro, sem I/O: concessionária ∩ veículo, exceção com prioridade sobre a regra recorrente,
 * fatiado em passos de `durationMinutes` na convenção `[start, end)`.
 *
 * @phpstan-type Interval array{start: \DateTimeImmutable, end: \DateTimeImmutable}
 */
final readonly class AvailabilityCalculator
{
    /** Concessionária sem nenhuma regra cadastrada ainda -- default de negócio, não config externo (mesmo espírito de `Appointment::DURATION_MINUTES`). */
    private const int DEFAULT_DEALERSHIP_START_HOUR = 9;
    private const int DEFAULT_DEALERSHIP_END_HOUR = 18;

    /**
     * @param list<WeeklyWindow> $dealershipWindows janelas recorrentes da concessionária, de qualquer weekday
     * @param list<WeeklyWindow> $vehicleWindows janelas recorrentes do veículo, de qualquer weekday
     * @param list<AvailabilityException> $exceptions só as da data pedida, dealership- e vehicle-scoped juntas
     * @param list<\DateTimeImmutable> $occupiedStarts horários já ocupados por agendamento ativo naquele veículo/data
     * @param ?\DateTimeImmutable $notBefore slot que já passou não é "válido" -- `null` não filtra (útil em teste)
     * @return list<\DateTimeImmutable>
     */
    public function slotsFor(
        \DateTimeImmutable $date,
        array $dealershipWindows,
        array $vehicleWindows,
        array $exceptions,
        array $occupiedStarts,
        int $durationMinutes,
        ?\DateTimeImmutable $notBefore = null,
    ): array {
        $weekday = (int) $date->format('w');

        $dealershipExceptions = array_values(array_filter($exceptions, static fn (AvailabilityException $e): bool => $e->dealershipId !== null));
        $vehicleExceptions = array_values(array_filter($exceptions, static fn (AvailabilityException $e): bool => $e->vehicleId !== null));

        // Sem regra E sem exceção: concessionária cai no default de negócio, veículo não restringe nada.
        // Exceção pontual, mesmo sem regra recorrente, já conta como "configurado".
        $dealershipUnconfigured = $dealershipWindows === [] && $dealershipExceptions === [];
        $vehicleUnconfigured = $vehicleWindows === [] && $vehicleExceptions === [];

        $dealershipIntervals = $this->effectiveIntervals(
            $date,
            $weekday,
            $dealershipUnconfigured ? $this->defaultDealershipWindows() : $dealershipWindows,
            $dealershipExceptions,
        );

        $intersected = $vehicleUnconfigured
            ? $dealershipIntervals
            : $this->intersectAll($dealershipIntervals, $this->effectiveIntervals($date, $weekday, $vehicleWindows, $vehicleExceptions));

        // Timestamp, não `format()`: dois `DateTimeImmutable` do mesmo instante em fusos diferentes
        // formatam string diferente -- comparar pelo instante é imune a isso.
        $occupied = array_map(static fn (\DateTimeImmutable $t): int => $t->getTimestamp(), $occupiedStarts);
        $slots = [];

        foreach ($intersected as $interval) {
            $cursor = $interval['start'];

            while ($cursor->modify("+{$durationMinutes} minutes") <= $interval['end']) {
                $isPast = $notBefore instanceof \DateTimeImmutable && $cursor < $notBefore;

                if (!$isPast && !in_array($cursor->getTimestamp(), $occupied, true)) {
                    $slots[] = $cursor;
                }

                $cursor = $cursor->modify("+{$durationMinutes} minutes");
            }
        }

        return $slots;
    }

    /**
     * @param list<WeeklyWindow> $windows
     * @param list<AvailabilityException> $exceptions já filtradas pro escopo certo (dealership XOR vehicle) e pra data
     * @return list<Interval>
     */
    private function effectiveIntervals(\DateTimeImmutable $date, int $weekday, array $windows, array $exceptions): array
    {
        $openOverride = array_values(array_filter($exceptions, static fn (AvailabilityException $e): bool => $e->isAvailable));

        if ($openOverride !== []) {
            return array_map(function (AvailabilityException $e) use ($date): array {
                // O construtor de AvailabilityException já exige os dois quando isAvailable=true.
                \assert($e->startTime instanceof \DateTimeImmutable && $e->endTime instanceof \DateTimeImmutable);

                return ['start' => $this->combine($date, $e->startTime), 'end' => $this->combine($date, $e->endTime)];
            }, $openOverride);
        }

        $fullDayBlocked = array_any(
            $exceptions,
            static fn (AvailabilityException $e): bool => !$e->isAvailable && !$e->startTime instanceof \DateTimeImmutable,
        );

        if ($fullDayBlocked) {
            return [];
        }

        $intervals = array_map(fn (WeeklyWindow $w): array => [
            'start' => $this->combine($date, $w->startTime),
            'end' => $this->combine($date, $w->endTime),
        ], array_values(array_filter($windows, static fn (WeeklyWindow $w): bool => $w->weekday === $weekday)));

        foreach ($exceptions as $exception) {
            if ($exception->isAvailable || !$exception->startTime instanceof \DateTimeImmutable || !$exception->endTime instanceof \DateTimeImmutable) {
                continue;
            }

            $intervals = $this->subtractFromAll($intervals, [
                'start' => $this->combine($date, $exception->startTime),
                'end' => $this->combine($date, $exception->endTime),
            ]);
        }

        return $intervals;
    }

    /**
     * @param list<Interval> $intervals
     * @param Interval $toSubtract
     * @return list<Interval>
     */
    private function subtractFromAll(array $intervals, array $toSubtract): array
    {
        $result = [];

        foreach ($intervals as $interval) {
            if ($toSubtract['end'] <= $interval['start'] || $toSubtract['start'] >= $interval['end']) {
                $result[] = $interval;

                continue;
            }

            if ($toSubtract['start'] > $interval['start']) {
                $result[] = ['start' => $interval['start'], 'end' => min($toSubtract['start'], $interval['end'])];
            }

            if ($toSubtract['end'] < $interval['end']) {
                $result[] = ['start' => max($toSubtract['end'], $interval['start']), 'end' => $interval['end']];
            }
        }

        return $result;
    }

    /**
     * @param list<Interval> $a
     * @param list<Interval> $b
     * @return list<Interval>
     */
    private function intersectAll(array $a, array $b): array
    {
        $result = [];

        foreach ($a as $intervalA) {
            foreach ($b as $intervalB) {
                $start = max($intervalA['start'], $intervalB['start']);
                $end = min($intervalA['end'], $intervalB['end']);

                if ($start < $end) {
                    $result[] = ['start' => $start, 'end' => $end];
                }
            }
        }

        return $result;
    }

    private function combine(\DateTimeImmutable $date, \DateTimeImmutable $time): \DateTimeImmutable
    {
        return $date->setTime((int) $time->format('H'), (int) $time->format('i'), (int) $time->format('s'));
    }

    /** @return list<WeeklyWindow> segunda(1) a sexta(5), {@see self::DEFAULT_DEALERSHIP_START_HOUR}-{@see self::DEFAULT_DEALERSHIP_END_HOUR} */
    private function defaultDealershipWindows(): array
    {
        $start = new \DateTimeImmutable()->setTime(self::DEFAULT_DEALERSHIP_START_HOUR, 0);
        $end = new \DateTimeImmutable()->setTime(self::DEFAULT_DEALERSHIP_END_HOUR, 0);

        return array_map(static fn (int $weekday): WeeklyWindow => new WeeklyWindow($weekday, $start, $end), [1, 2, 3, 4, 5]);
    }
}
