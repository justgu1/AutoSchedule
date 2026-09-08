<?php

declare(strict_types=1);

namespace App\Application\Shared;

use App\Domain\Shared\Address;
use App\Domain\Shared\Uf;

/** Único mapa entre os campos da API e o VO, nas duas direções -- `address` é a rua, por contrato anterior ao VO. */
final readonly class AddressFields
{
    /** Sem `$current` todo campo é obrigatório (criação); com ele, o ausente mantém o valor gravado (edição parcial). */
    public static function from(ValidatedInput $data, ?Address $current = null): Address
    {
        return new Address(
            zipCode: self::read($data, 'zip_code', $current?->zipCode),
            street: self::read($data, 'address', $current?->street),
            number: self::read($data, 'number', $current?->number),
            complement: $data->stringOrNull('complement') ?? $current?->complement,
            neighborhood: self::read($data, 'neighborhood', $current?->neighborhood),
            city: self::read($data, 'city', $current?->city),
            state: Uf::from(self::read($data, 'state', $current?->state->value)),
        );
    }

    /** @return array<string, ?string> */
    public static function toArray(Address $address): array
    {
        return [
            'zip_code' => $address->zipCode,
            'address' => $address->street,
            'number' => $address->number,
            'complement' => $address->complement,
            'neighborhood' => $address->neighborhood,
            'city' => $address->city,
            'state' => $address->state->value,
        ];
    }

    private static function read(ValidatedInput $data, string $field, ?string $current): string
    {
        return $current === null ? $data->string($field) : $data->stringOr($field, $current);
    }
}
