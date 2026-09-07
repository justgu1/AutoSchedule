<?php

declare(strict_types=1);

namespace App\Application\Address;

use App\Domain\Address\Ports\ZipCodeCacheRepository;
use App\Domain\Address\Ports\ZipCodeProvider;
use App\Domain\Address\ZipCodeAddress;

/** Cache-aside: só chama o provedor externo na primeira vez que um CEP aparece, depois é sempre o cache do próprio banco. */
final readonly class LookupZipCode
{
    public function __construct(
        private ZipCodeCacheRepository $cache,
        private ZipCodeProvider $provider,
    ) {
    }

    public function __invoke(string $zipCode): ?ZipCodeAddress
    {
        $cached = $this->cache->find($zipCode);

        if ($cached instanceof ZipCodeAddress) {
            return $cached;
        }

        $address = $this->provider->lookup($zipCode);

        if ($address instanceof ZipCodeAddress) {
            $this->cache->save($zipCode, $address);
        }

        return $address;
    }
}
