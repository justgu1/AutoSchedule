<?php

declare(strict_types=1);

namespace Tests\Application\Vehicle;

use App\Application\Dealership\DealershipFinder;
use App\Application\Shared\ActorContext;
use App\Application\Vehicle\DTO\PublicVehicleProfile;
use App\Application\Vehicle\DTO\VehicleProfile;
use App\Application\Vehicle\VehicleFinder;
use App\Application\Vehicle\VehicleGallery;
use App\Application\Vehicle\ViewVehicle;
use App\Domain\Dealership\Dealership;
use App\Domain\Shared\Address;
use App\Domain\Shared\Money;
use App\Domain\Shared\Uf;
use App\Domain\User\UserRole;
use App\Domain\Vehicle\Vehicle;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\InMemoryDealershipRepository;
use Tests\Support\InMemoryFileRepository;
use Tests\Support\InMemoryVehicleImageRepository;
use Tests\Support\InMemoryVehicleRepository;

final class ViewVehicleTest extends TestCase
{
    private Dealership $dealership;
    private Vehicle $vehicle;
    private ViewVehicle $viewVehicle;

    protected function setUp(): void
    {
        $this->dealership = Dealership::register(
            ownerUserId: 'seller-1',
            name: 'Auto Center',
            address: new Address('01000-000', 'Rua Antiga', '10', null, 'Bairro', 'Cidade', Uf::SP),
            phone: null,
        );
        $dealerships = new InMemoryDealershipRepository([$this->dealership->id => $this->dealership]);
        $this->vehicle = Vehicle::register($this->dealership->id, 'Chevrolet', 'Onix', 'LTZ', 2023, new Money(8990000));
        $vehicles = new InMemoryVehicleRepository();
        $vehicles->insert($this->vehicle);
        $gallery = new VehicleGallery(new InMemoryVehicleImageRepository(), new InMemoryFileRepository(), new NoopStorageProvider());

        $this->viewVehicle = new ViewVehicle(new VehicleFinder($vehicles), $gallery, new DealershipFinder($dealerships));
    }

    #[Test]
    public function dono_recebe_o_perfil_completo(): void
    {
        $profile = ($this->viewVehicle)($this->vehicle->id, new ActorContext('seller-1', UserRole::Seller));

        $this->assertInstanceOf(VehicleProfile::class, $profile);
        $this->assertSame($this->dealership->id, $profile->dealershipId);
    }

    #[Test]
    public function admin_recebe_o_perfil_completo_mesmo_sem_ser_dono(): void
    {
        $profile = ($this->viewVehicle)($this->vehicle->id, new ActorContext('admin-1', UserRole::Admin));

        $this->assertInstanceOf(VehicleProfile::class, $profile);
    }

    #[Test]
    public function visitante_sem_conta_recebe_o_perfil_publico_com_a_concessionaria_aninhada(): void
    {
        $profile = ($this->viewVehicle)($this->vehicle->id, new ActorContext());

        $this->assertInstanceOf(PublicVehicleProfile::class, $profile);
        $this->assertSame($this->dealership->slug, $profile->dealershipSlug);
        $this->assertSame($this->dealership->name, $profile->dealershipName);
        $this->assertArrayNotHasKey('dealership_id', $profile->toArray());
        $this->assertArrayNotHasKey('status', $profile->toArray());
    }

    #[Test]
    public function outro_seller_tambem_recebe_o_perfil_publico(): void
    {
        $profile = ($this->viewVehicle)($this->vehicle->id, new ActorContext('seller-2', UserRole::Seller));

        $this->assertInstanceOf(PublicVehicleProfile::class, $profile);
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
