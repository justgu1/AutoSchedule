<?php

declare(strict_types=1);

namespace Tests\Application\Dealership;

use App\Application\Dealership\DealershipFinder;
use App\Application\Dealership\DealershipPhotos;
use App\Application\Dealership\DTO\PublicDealershipProfile;
use App\Application\Dealership\ViewDealership;
use App\Application\Shared\ActorContext;
use App\Application\Vehicle\VehicleGallery;
use App\Domain\Dealership\Dealership;
use App\Domain\Shared\Address;
use App\Domain\Shared\Money;
use App\Domain\Shared\Uf;
use App\Domain\User\Ports\UserRepository;
use App\Domain\User\User;
use App\Domain\Vehicle\Vehicle;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\InMemoryDealershipRepository;
use Tests\Support\InMemoryFileRepository;
use Tests\Support\InMemoryVehicleImageRepository;
use Tests\Support\InMemoryVehicleRepository;

final class ViewDealershipPublicVehiclesTest extends TestCase
{
    private Dealership $dealership;
    private InMemoryVehicleRepository $vehicles;
    private ViewDealership $viewDealership;

    protected function setUp(): void
    {
        $this->dealership = Dealership::register(
            ownerUserId: 'seller-1',
            name: 'Auto Center',
            address: new Address('01000-000', 'Rua Antiga', '10', null, 'Bairro', 'Cidade', Uf::SP),
            phone: null,
        );
        $dealerships = new InMemoryDealershipRepository([$this->dealership->id => $this->dealership]);
        $this->vehicles = new InMemoryVehicleRepository([$this->dealership->id => 'seller-1']);
        $gallery = new VehicleGallery(new InMemoryVehicleImageRepository(), new InMemoryFileRepository(), new NoopStorageProvider());

        $this->viewDealership = new ViewDealership(
            new DealershipFinder($dealerships),
            new DealershipPhotos(new InMemoryFileRepository(), new NoopStorageProvider()),
            new NullUserRepository(),
            $this->vehicles,
            $gallery,
        );
    }

    #[Test]
    public function perfil_publico_lista_os_veiculos_ativos_da_concessionaria(): void
    {
        $active = $this->register('Chevrolet', 'Onix');
        $trashed = $this->register('Fiat', 'Argo');
        $this->vehicles->trash($trashed->id);

        $profile = ($this->viewDealership)($this->dealership->id, new ActorContext());

        $this->assertInstanceOf(PublicDealershipProfile::class, $profile);
        $this->assertCount(1, $profile->vehicles);
        $this->assertSame($active->id, $profile->vehicles[0]->id);
        $this->assertSame(1, $profile->vehiclesTotal);
    }

    #[Test]
    public function perfil_publico_limita_a_vitrine_e_devolve_o_total_separado(): void
    {
        for ($i = 0; $i < 15; ++$i) {
            $this->register('Chevrolet', 'Onix');
        }

        $profile = ($this->viewDealership)($this->dealership->id, new ActorContext());

        $this->assertInstanceOf(PublicDealershipProfile::class, $profile);
        $this->assertCount(12, $profile->vehicles);
        $this->assertSame(15, $profile->vehiclesTotal);
    }

    private function register(string $brand, string $model): Vehicle
    {
        $vehicle = Vehicle::register($this->dealership->id, $brand, $model, null, 2023, 2023, new Money(8990000));
        $this->vehicles->insert($vehicle);

        return $vehicle;
    }
}

final class NoopStorageProvider implements \App\Domain\File\Ports\StorageProvider
{
    public function put(string $path, string $contents, string $mimeType): void
    {
    }

    public function url(string $path): string
    {
        return 'https://storage.test/' . $path;
    }

    public function delete(string $path): void
    {
    }
}

/** Nome do vendedor não é o que este teste verifica -- só precisa não lançar. */
final class NullUserRepository implements UserRepository
{
    public function findById(string $id): ?User
    {
        return null;
    }

    public function findByEmail(\App\Domain\Shared\Email $email): ?User
    {
        return null;
    }

    public function existsByEmail(\App\Domain\Shared\Email $email): bool
    {
        return false;
    }

    public function insert(User $user): void
    {
    }

    public function update(User $user): void
    {
    }

    public function trash(string $id): void
    {
    }

    public function restore(string $id): void
    {
    }

    public function findPage(int $limit, int $offset, ?string $role = null): array
    {
        return [];
    }

    public function count(?string $role = null): int
    {
        return 0;
    }

    public function countByRole(\App\Domain\User\UserRole $role): int
    {
        return 0;
    }

    public function findTrashed(): array
    {
        return [];
    }

    public function purge(\App\Domain\Shared\Trashable $entity): void
    {
    }
}
