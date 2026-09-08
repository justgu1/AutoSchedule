<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Address\LookupZipCode;
use App\Domain\Address\ZipCodeAddress;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;

/**
 * Proxy do próprio backend pro ViaCEP -- o front nunca chama o terceiro
 * direto. Primeira consulta de um CEP resolve de fora, o resto vem do
 * cache (`zip_code_cache`), sem depender do ViaCEP de novo.
 */
final readonly class ZipCodeController
{
    public function __construct(private LookupZipCode $lookup)
    {
    }

    public function show(Request $request): Response
    {
        $zipCode = preg_replace('/\D/', '', (string) $request->param('zip_code')) ?? '';

        if (strlen($zipCode) !== 8) {
            throw new DomainException('Invalid data.', DomainErrorType::Validation, ['zip_code' => 'CEP must have 8 digits.']);
        }

        $address = ($this->lookup)($zipCode);

        if (!$address instanceof ZipCodeAddress) {
            throw new DomainException('CEP not found.', DomainErrorType::NotFound);
        }

        return Response::success([
            'street' => $address->street,
            'neighborhood' => $address->neighborhood,
            'city' => $address->city,
            'state' => $address->state,
        ]);
    }
}
