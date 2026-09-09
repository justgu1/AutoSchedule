<?php

declare(strict_types=1);

namespace Tests\Application\Vehicle;

use App\Application\Dealership\DealershipFinder;
use App\Application\Shared\ActorContext;
use App\Application\Shared\ValidatedInput;
use App\Application\Vehicle\UpdateVehicle;
use App\Application\Vehicle\VehicleAmenities;
use App\Application\Vehicle\VehicleFinder;
use App\Domain\Audit\AuditEvent;
use App\Domain\Dealership\Dealership;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
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
use Tests\Support\InMemoryVehicleAmenityCatalog;
use Tests\Support\InMemoryVehicleAmenityLinkRepository;
use Tests\Support\InMemoryVehicleRepository;

final class UpdateVehicleTest extends TestCase
{
    private InMemoryDealershipRepository $dealerships;
    private InMemoryVehicleRepository $vehicles;
    private FakeAuditLogger $audit;
    private UpdateVehicle $updateVehicle;
    private Dealership $origin;
    private Vehicle $vehicle;

    protected function setUp(): void
    {
        $this->dealerships = new InMemoryDealershipRepository();
        $this->vehicles = new InMemoryVehicleRepository();
        $this->audit = new FakeAuditLogger();
        $amenities = new VehicleAmenities(new InMemoryVehicleAmenityCatalog(), new InMemoryVehicleAmenityLinkRepository(), new DirectTransaction());
        $this->updateVehicle = new UpdateVehicle(
            new VehicleFinder($this->vehicles),
            new DealershipFinder($this->dealerships),
            $this->vehicles,
            $amenities,
            $this->audit,
        );

        $this->origin = $this->registerDealership('Origem');
        $this->vehicle = Vehicle::register($this->origin->id, 'Chevrolet', 'Onix', 'LTZ 1.0 Turbo', 2023, 2023, new Money(8990000));
        $this->vehicles->insert($this->vehicle);
    }

    #[Test]
    public function campo_ausente_mantem_o_valor_gravado(): void
    {
        $profile = ($this->updateVehicle)($this->vehicle->id, new ValidatedInput(['price' => '79900.00']), null, $this->actor());

        $this->assertSame('79900.00', $profile->price->toDecimal());
        $this->assertSame('Chevrolet', $profile->brand);
        $this->assertSame('LTZ 1.0 Turbo', $profile->version);
        $this->assertSame(2023, $profile->modelYear);
    }

    #[Test]
    public function mover_de_concessionaria_gera_evento_de_auditoria_proprio_alem_do_update(): void
    {
        $destination = $this->registerDealership('Destino');

        $profile = ($this->updateVehicle)(
            $this->vehicle->id,
            new ValidatedInput(['dealership_id' => $destination->id]),
            null,
            $this->actor(),
        );

        $this->assertSame($destination->id, $profile->dealershipId);
        $this->assertSame([AuditEvent::VehicleUpdated, AuditEvent::VehicleDealershipReassigned], $this->audit->events);
    }

    #[Test]
    public function update_sem_troca_de_concessionaria_nao_gera_o_evento_de_movimentacao(): void
    {
        ($this->updateVehicle)(
            $this->vehicle->id,
            new ValidatedInput(['dealership_id' => $this->origin->id, 'brand' => 'Fiat']),
            null,
            $this->actor(),
        );

        $this->assertSame([AuditEvent::VehicleUpdated], $this->audit->events);
    }

    /** Concessionária que quem chama não alcança é 404 -- é o que impede mover pro estoque alheio. */
    #[Test]
    public function mover_pra_concessionaria_inalcancavel_e_404_e_nao_persiste_nada(): void
    {
        try {
            ($this->updateVehicle)(
                $this->vehicle->id,
                new ValidatedInput(['dealership_id' => '11111111-1111-4111-8111-111111111111']),
                null,
                $this->actor(),
            );
            $this->fail('Expected a not found error.');
        } catch (DomainException $exception) {
            $this->assertSame(DomainErrorType::NotFound, $exception->type());
        }

        $this->assertSame($this->origin->id, $this->vehicles->findById($this->vehicle->id)?->dealershipId);
        $this->assertSame([], $this->audit->events);
    }

    private function registerDealership(string $name): Dealership
    {
        $dealership = Dealership::register(
            ownerUserId: 'seller-1',
            name: $name,
            address: new Address('01000-000', 'Rua Antiga', '10', null, 'Bairro', 'Cidade', Uf::SP),
            phone: null,
        );
        $this->dealerships->insert($dealership);

        return $dealership;
    }

    private function actor(): ActorContext
    {
        return new ActorContext('seller-1', UserRole::Seller);
    }
}
