<?php

declare(strict_types=1);

namespace Tests\Domain\Vehicle;

use App\Domain\Shared\Money;
use App\Domain\Shared\TrashableStatus;
use App\Domain\Shared\TrashState;
use App\Domain\Vehicle\Vehicle;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class VehicleTest extends TestCase
{
    #[Test]
    public function register_monta_um_veiculo_novo_ativo_e_sem_anonimizacao(): void
    {
        $vehicle = $this->registerFixture();

        $this->assertNotSame('', $vehicle->id);
        $this->assertSame('dealership-1', $vehicle->dealershipId);
        $this->assertTrue($vehicle->trash->isActive());
        $this->assertFalse($vehicle->trashedByDealershipTrash);
        $this->assertNull($vehicle->trash->anonymizedAt);
    }

    #[Test]
    public function with_details_troca_os_dados_mas_preserva_concessionaria_e_ciclo_de_vida(): void
    {
        $vehicle = $this->registerFixture();

        $updated = $vehicle->withDetails(
            brand: 'Fiat',
            model: 'Argo',
            version: 'Drive 1.3',
            manufactureYear: 2023,
            modelYear: 2024,
            price: new Money(7550000),
            description: 'Único dono',
            mileageKm: null,
            transmission: null,
            bodyType: null,
            fuelType: null,
            color: null,
            plateEndDigit: null,
            acceptsTrade: false,
            ipvaPaid: false,
            licensed: false,
        );

        $this->assertSame('Fiat', $updated->brand);
        $this->assertSame('Argo', $updated->model);
        $this->assertSame(7550000, $updated->price->cents);
        $this->assertSame('Único dono', $updated->description);
        $this->assertSame($vehicle->id, $updated->id);
        $this->assertSame($vehicle->dealershipId, $updated->dealershipId);
        $this->assertTrue($updated->trash->isActive());
    }

    #[Test]
    public function allows_restore_permite_so_trashed_ainda_nao_anonimizado(): void
    {
        $this->assertTrue($this->trashedFixture(new \DateTimeImmutable(), null)->trash->allowsRestore());
        $this->assertFalse($this->trashedFixture(new \DateTimeImmutable(), new \DateTimeImmutable())->trash->allowsRestore());
        $this->assertFalse($this->registerFixture()->trash->allowsRestore());
    }

    #[Test]
    public function allows_purge_exige_trashed_ha_mais_de_grace_days(): void
    {
        $now = new \DateTimeImmutable();

        $this->assertTrue($this->trashedFixture($now->modify('-31 days'), null)->trash->allowsPurge($now));
        $this->assertFalse($this->trashedFixture($now->modify('-29 days'), null)->trash->allowsPurge($now));
    }

    #[Test]
    public function anonymized_encerra_o_ciclo_de_vida_sem_mexer_nos_dados_do_anuncio(): void
    {
        $trashed = $this->trashedFixture(new \DateTimeImmutable('-40 days'), null);

        $purged = $trashed->anonymized();

        $this->assertSame(TrashableStatus::Deleted, $purged->trash->status);
        $this->assertInstanceOf(\DateTimeImmutable::class, $purged->trash->anonymizedAt);
        $this->assertSame($trashed->brand, $purged->brand);
        $this->assertSame($trashed->model, $purged->model);
        $this->assertSame($trashed->price->cents, $purged->price->cents);
    }

    private function registerFixture(): Vehicle
    {
        return Vehicle::register('dealership-1', 'Chevrolet', 'Onix', 'LTZ 1.0 Turbo', 2023, 2023, new Money(8990000));
    }

    private function trashedFixture(\DateTimeImmutable $trashedAt, ?\DateTimeImmutable $anonymizedAt): Vehicle
    {
        $vehicle = $this->registerFixture();
        $status = $anonymizedAt instanceof \DateTimeImmutable ? TrashableStatus::Deleted : TrashableStatus::Trashed;

        return new Vehicle(
            id: $vehicle->id,
            dealershipId: $vehicle->dealershipId,
            brand: $vehicle->brand,
            model: $vehicle->model,
            version: $vehicle->version,
            manufactureYear: $vehicle->manufactureYear,
            modelYear: $vehicle->modelYear,
            price: $vehicle->price,
            description: $vehicle->description,
            mileageKm: $vehicle->mileageKm,
            transmission: $vehicle->transmission,
            bodyType: $vehicle->bodyType,
            fuelType: $vehicle->fuelType,
            color: $vehicle->color,
            plateEndDigit: $vehicle->plateEndDigit,
            acceptsTrade: $vehicle->acceptsTrade,
            ipvaPaid: $vehicle->ipvaPaid,
            licensed: $vehicle->licensed,
            trash: new TrashState($status, $trashedAt, $anonymizedAt),
            trashedByDealershipTrash: false,
            createdAt: $vehicle->createdAt,
            updatedAt: $vehicle->updatedAt,
        );
    }
}
