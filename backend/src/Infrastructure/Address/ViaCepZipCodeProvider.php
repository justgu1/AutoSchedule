<?php

declare(strict_types=1);

namespace App\Infrastructure\Address;

use App\Domain\Address\Ports\ZipCodeProvider;
use App\Domain\Address\ZipCodeAddress;

/**
 * ViaCEP (gratuito, sem chave) -- sem SDK, só `file_get_contents` como o
 * `GoogleJwksIdTokenVerifier` já faz pra outra integração externa. CEP
 * inválido/fora dos Correios vem com `erro: true` no corpo, HTTP 200 mesmo
 * assim -- não é exceção de rede, é resultado "não encontrado" de verdade.
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
