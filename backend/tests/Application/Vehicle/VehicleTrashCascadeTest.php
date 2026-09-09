<?php

declare(strict_types=1);

namespace Tests\Application\Vehicle;

use App\Application\Dealership\DealershipFinder;
use App\Application\Dealership\RestoreDealership;
use App\Application\Dealership\TrashDealership;
use App\Application\Shared\ActorContext;
use App\Application\Vehicle\TrashVehicle;
use App\Application\Vehicle\VehicleFinder;
use App\Domain\Dealership\Dealership;
use App\Domain\Shared\Address;
use App\Domain\Shared\Money;
use App\Domain\Shared\Uf;
use App\Domain\User\UserRole;
use App\Domain\Vehicle\Vehicle;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\DirectTransaction;
use Tests\Support\FakeAuditLogger;
use Tests\Support\InMemoryDealershipRepository;
use Tests\Support\InMemoryVehicleRepository;

/** A cascata é o que impede estoque órfão aparecendo sozinho quando a concessionária sai do ar. */
final class VehicleTrashCascadeTest extends TestCase
{
    private InMemoryDealershipRepository $dealerships;
    private InMemoryVehicleRepository $vehicles;
    private Dealership $dealership;

    protected function setUp(): void
    {
        $this->dealership = Dealership::register(
            ownerUserId: 'seller-1',
            name: 'Auto Center',
            address: new Address('01000-000', 'Rua Antiga', '10', null, 'Bairro', 'Cidade', Uf::SP),
            phone: null,
        );
        $this->dealerships = new InMemoryDealershipRepository();
        $this->dealerships->insert($this->dealership);
        $this->vehicles = new InMemoryVehicleRepository([$this->dealership->id => 'seller-1']);
    }

    #[Test]
    public function lixeira_da_concessionaria_arrasta_os_veiculos_ativos_dela(): void
    {
        $vehicle = $this->registerVehicle();

        $this->trashDealership();

        $trashed = $this->vehicles->findById($vehicle->id);
        $this->assertNotNull($trashed);
        $this->assertTrue($trashed->trash->isTrashed());
        $this->assertTrue($trashed->trashedByDealershipTrash);
    }

    #[Test]
    public function restaurar_a_concessionaria_devolve_o_veiculo_que_caiu_por_cascata(): void
    {
        $vehicle = $this->registerVehicle();
        $this->trashDealership();

        $this->restoreDealership();

        $restored = $this->vehicles->findById($vehicle->id);
        $this->assertNotNull($restored);
        $this->assertTrue($restored->trash->isActive());
    }

    #[Test]
    public function veiculo_arquivado_manualmente_nao_volta_com_a_concessionaria(): void
    {
        $vehicle = $this->registerVehicle();
        $this->trashVehicle($vehicle);
        $this->trashDealership();

        $this->restoreDealership();

        $stillTrashed = $this->vehicles->findById($vehicle->id);
        $this->assertNotNull($stillTrashed);
        $this->assertTrue($stillTrashed->trash->isTrashed());
    }

    private function registerVehicle(): Vehicle
    {
        $vehicle = Vehicle::register($this->dealership->id, 'Chevrolet', 'Onix', null, 2023, 2023, new Money(8990000));
        $this->vehicles->insert($vehicle);

        return $vehicle;
    }

    private function trashVehicle(Vehicle $vehicle): void
    {
        (new TrashVehicle(new VehicleFinder($this->vehicles), $this->vehicles, new FakeAuditLogger()))($vehicle->id, $this->actor());
    }

    private function trashDealership(): void
    {
        (new TrashDealership(
            new DealershipFinder($this->dealerships),
            $this->dealerships,
            $this->vehicles,
            new FakeAuditLogger(),
            new DirectTransaction(),
        ))($this->dealership->id, $this->actor());
    }

    private function restoreDealership(): void
    {
        (new RestoreDealership(
            new DealershipFinder($this->dealerships),
            $this->dealerships,
            $this->vehicles,
            new FakeAuditLogger(),
            new DirectTransaction(),
        ))($this->dealership->id, $this->actor());
    }

    private function actor(): ActorContext
    {
        return new ActorContext('seller-1', UserRole::Seller);
    }
}
