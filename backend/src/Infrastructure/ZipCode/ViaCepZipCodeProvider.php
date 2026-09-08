<?php

declare(strict_types=1);

namespace App\Infrastructure\ZipCode;

use App\Domain\ZipCode\Ports\ZipCodeProvider;
use App\Domain\ZipCode\ZipCodeAddress;

/**
 * CEP inexistente vem com `erro: true` e HTTP 200, então "não encontrado" não é falha de rede.
 */
final readonly class ViaCepZipCodeProvider implements ZipCodeProvider
{
    private const string BASE_URL = 'https://viacep.com.br/ws/%s/json/';

    public function lookup(string $zipCode): ?ZipCodeAddress
    {
        $raw = @file_get_contents(sprintf(self::BASE_URL, $zipCode), context: stream_context_create([
            'http' => ['timeout' => 5],
        ]));

        if ($raw === false) {
            return null;
        }

        try {
            $data = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!is_array($data) || ($data['erro'] ?? false) === true) {
            return null;
        }

        return new ZipCodeAddress(
            street: $this->stringField($data, 'logradouro'),
            neighborhood: $this->stringField($data, 'bairro'),
            city: $this->stringField($data, 'localidade'),
            state: $this->stringField($data, 'uf'),
        );
    }

    /** @param array<mixed> $data */
    private function stringField(array $data, string $key): string
    {
        $value = $data[$key] ?? '';

        return is_string($value) ? $value : '';
    }
}
