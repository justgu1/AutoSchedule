<?php

declare(strict_types=1);

namespace App\Domain\Address\Ports;

use App\Domain\Address\ZipCodeAddress;

interface ZipCodeCacheRepository
{
    public function find(string $zipCode): ?ZipCodeAddress;

    /** CEP não muda de endereço -- sem TTL, sem expiração; grava uma vez e reaproveita pra sempre. */
    public function save(string $zipCode, ZipCodeAddress $address): void;
}
