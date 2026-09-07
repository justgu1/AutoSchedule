<?php

declare(strict_types=1);

namespace App\Domain\Address\Ports;

use App\Domain\Address\ZipCodeAddress;

interface ZipCodeProvider
{
    /** Null quando o CEP não existe/não é dos Correios, ou o provedor está fora do ar -- nenhum dos dois é motivo pra derrubar a request de quem chamou. */
    public function lookup(string $zipCode): ?ZipCodeAddress;
}
