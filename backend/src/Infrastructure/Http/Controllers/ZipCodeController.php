<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\ZipCode\LookupZipCode;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Domain\ZipCode\ZipCodeAddress;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;

/** Proxy pra o front nunca chamar o terceiro direto; o cache é responsabilidade de `LookupZipCode`. */
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
