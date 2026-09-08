<?php

declare(strict_types=1);

namespace Tests\Application\Vehicle;

use App\Application\Dealership\DealershipFinder;
use App\Application\Shared\ActorContext;
use App\Application\Shared\ValidatedInput;
use App\Application\Vehicle\CreateVehicle;
use App\Domain\Audit\AuditEvent;
use App\Domain\Dealership\Dealership;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Domain\Shared\Address;
use App\Domain\Shared\Uf;
use App\Domain\User\UserRole;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeAuditLogger;
use Tests\Support\InMemoryDealershipRepository;
use Tests\Support\InMemoryVehicleRepository;

final class CreateVehicleTest extends TestCase
{
    private InMemoryDealershipRepository $dealerships;
    private InMemoryVehicleRepository $vehicles;
    private FakeAuditLogger $audit;
    private CreateVehicle $createVehicle;

    protected function setUp(): void
    {
        $this->dealerships = new InMemoryDealershipRepository();
        $this->vehicles = new InMemoryVehicleRepository();
        $this->audit = new FakeAuditLogger();
        $this->createVehicle = new CreateVehicle(new DealershipFinder($this->dealerships), $this->vehicles, $this->audit);
    }

    #[Test]
    public function seller_cria_veiculo_na_propria_concessionaria(): void
    {
        $dealership = $this->registerDealership('seller-1');

        $profile = ($this->createVehicle)($this->input($dealership->id), $this->actor('seller-1', UserRole::Seller));

        $this->assertSame($dealership->id, $profile->dealershipId);
        $this->assertSame('Chevrolet', $profile->brand);
        $this->assertSame('89900.00', $profile->toArray()['price']);
        $this->assertNotNull($this->vehicles->findById($profile->id));
        $this->assertSame([AuditEvent::VehicleCreated], $this->audit->events);
    }

    /** O RLS esconde a concessionária alheia, então o caso de uso só precisa deixar o 404 subir. */
    #[Test]
    public function seller_nao_cria_veiculo_em_concessionaria_que_nao_e_dele(): void
    {
        try {
            ($this->createVehicle)($this->input('11111111-1111-4111-8111-111111111111'), $this->actor('seller-1', UserRole::Seller));
            $this->fail('Expected a not found error.');
        } catch (DomainException $exception) {
            $this->assertSame(DomainErrorType::NotFound, $exception->type());
        }

        $this->assertSame([], $this->audit->events);
    }

    #[Test]
    public function admin_cria_veiculo_em_qualquer_concessionaria(): void
    {
        $dealership = $this->registerDealership('seller-1');

        $profile = ($this->createVehicle)($this->input($dealership->id), $this->actor('admin-1', UserRole::Admin));

        $this->assertSame($dealership->id, $profile->dealershipId);
    }

    #[Test]
    public function ano_e_preco_chegam_como_numero_do_json_sem_quebrar(): void
    {
        $dealership = $this->registerDealership('seller-1');

        $profile = ($this->createVehicle)(
            new ValidatedInput(['dealership_id' => $dealership->id, 'brand' => 'Fiat', 'model' => 'Argo', 'year' => 2024, 'price' => 75500.5]),
            $this->actor('seller-1', UserRole::Seller),
        );

        $this->assertSame(2024, $profile->year);
        $this->assertSame('75500.50', $profile->toArray()['price']);
    }

    private function registerDealership(string $ownerUserId): Dealership
    {
        $dealership = Dealership::register(
            ownerUserId: $ownerUserId,
            name: 'Auto Center',
            address: new Address('01000-000', 'Rua Antiga', '10', null, 'Bairro', 'Cidade', Uf::SP),
            phone: null,
        );
        $this->dealerships->insert($dealership);

        return $dealership;
    }

    private function input(string $dealershipId): ValidatedInput
    {
        return new ValidatedInput([
            'dealership_id' => $dealershipId,
            'brand' => 'Chevrolet',
            'model' => 'Onix',
            'version' => 'LTZ 1.0 Turbo',
            'year' => 2023,
            'price' => '89900.00',
        ]);
    }

    private function actor(string $actorId, UserRole $role): ActorContext
    {
        return new ActorContext($actorId, $role);
    }
}
