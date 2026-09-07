<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Address;

use App\Domain\Address\Ports\ZipCodeCacheRepository;
use App\Domain\Address\Ports\ZipCodeProvider;
use App\Domain\Address\ZipCodeAddress;
use App\Infrastructure\Address\ZipCodeLookupService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ZipCodeLookupServiceTest extends TestCase
{
    #[Test]
    public function cache_hit_devolve_sem_chamar_o_provedor(): void
    {
        $address = new ZipCodeAddress('Rua de Teste', 'Centro', 'São Paulo', 'SP');
        $cache = new InMemoryZipCodeCacheRepository(['01000000' => $address]);
        $provider = new SpyZipCodeProvider(null);

        $result = new ZipCodeLookupService($cache, $provider)->resolve('01000000');

        $this->assertSame($address, $result);
        $this->assertSame(0, $provider->calls);
    }

    #[Test]
    public function cache_miss_chama_o_provedor_e_grava_o_resultado(): void
    {
        $address = new ZipCodeAddress('Rua Nova', 'Centro', 'Rio de Janeiro', 'RJ');
        $cache = new InMemoryZipCodeCacheRepository();
        $provider = new SpyZipCodeProvider($address);

        $result = new ZipCodeLookupService($cache, $provider)->resolve('20000000');

        $this->assertSame($address, $result);
        $this->assertSame(1, $provider->calls);
        $this->assertSame($address, $cache->find('20000000'));
    }

    #[Test]
    public function cep_inexistente_devolve_null_sem_gravar_nada(): void
    {
        $cache = new InMemoryZipCodeCacheRepository();
        $provider = new SpyZipCodeProvider(null);

        $result = new ZipCodeLookupService($cache, $provider)->resolve('99999999');

        $this->assertNull($result);
        $this->assertNull($cache->find('99999999'));
    }
}

final class InMemoryZipCodeCacheRepository implements ZipCodeCacheRepository
{
    /** @param array<string, ZipCodeAddress> $entries */
    public function __construct(private array $entries = [])
    {
    }

    public function find(string $zipCode): ?ZipCodeAddress
    {
        return $this->entries[$zipCode] ?? null;
    }

    public function save(string $zipCode, ZipCodeAddress $address): void
    {
        $this->entries[$zipCode] = $address;
    }
}

final class SpyZipCodeProvider implements ZipCodeProvider
{
    public int $calls = 0;

    public function __construct(private readonly ?ZipCodeAddress $result)
    {
    }

    public function lookup(string $zipCode): ?ZipCodeAddress
    {
        $this->calls++;

        return $this->result;
    }
}
