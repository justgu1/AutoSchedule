<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Domain\Shared\Money;
use App\Domain\Vehicle\VehicleFilters;

/** Ponte query string -> filtro de domínio, no mesmo espírito de `RequestActor`. */
final class VehicleFilterQuery
{
    public static function fromRequest(Request $request): VehicleFilters
    {
        return new VehicleFilters(
            term: self::text($request, 'q'),
            brand: self::text($request, 'brand'),
            model: self::text($request, 'model'),
            yearMin: self::year($request, 'year_min'),
            yearMax: self::year($request, 'year_max'),
            priceMin: self::price($request, 'price_min'),
            priceMax: self::price($request, 'price_max'),
            dealershipId: self::text($request, 'dealership_id'),
        );
    }

    private static function text(Request $request, string $name): ?string
    {
        $value = trim((string) $request->query($name));

        return $value === '' ? null : $value;
    }

    /** Faixa fora do plausível é ruído de query string, não erro de quem digitou: vira filtro ausente. */
    private static function year(Request $request, string $name): ?int
    {
        $value = self::text($request, $name);

        if ($value === null || !is_numeric($value)) {
            return null;
        }

        $year = (int) $value;

        return $year >= 1900 && $year <= 2100 ? $year : null;
    }

    private static function price(Request $request, string $name): ?Money
    {
        $value = self::text($request, $name);

        return $value === null || !is_numeric($value) ? null : Money::fromDecimal($value);
    }
}
