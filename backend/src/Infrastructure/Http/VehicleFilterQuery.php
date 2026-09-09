<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Domain\Shared\Money;
use App\Domain\Vehicle\BodyType;
use App\Domain\Vehicle\FuelType;
use App\Domain\Vehicle\Transmission;
use App\Domain\Vehicle\VehicleFilters;
use App\Domain\Vehicle\VehicleSort;

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
            sort: self::enum($request, 'sort', VehicleSort::class),
            transmission: self::enum($request, 'transmission', Transmission::class),
            bodyType: self::enum($request, 'body_type', BodyType::class),
            fuelType: self::enum($request, 'fuel_type', FuelType::class),
            mileageKmMax: self::mileage($request),
        );
    }

    /**
     * Valor não reconhecido é ruído de query string, não erro de quem chamou: vira filtro ausente.
     *
     * @template T of \BackedEnum
     * @param class-string<T> $enum
     * @return ?T
     */
    private static function enum(Request $request, string $name, string $enum): ?\BackedEnum
    {
        $value = self::text($request, $name);

        return $value === null ? null : $enum::tryFrom($value);
    }

    private static function mileage(Request $request): ?int
    {
        $value = self::text($request, 'mileage_km_max');

        return $value === null || !is_numeric($value) ? null : (int) $value;
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
