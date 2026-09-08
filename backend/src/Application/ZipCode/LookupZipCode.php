<?php

declare(strict_types=1);

namespace App\Application\ZipCode;

use App\Domain\ZipCode\Ports\ZipCodeCacheRepository;
use App\Domain\ZipCode\Ports\ZipCodeProvider;
use App\Domain\ZipCode\ZipCodeAddress;

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
