<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Persistence;

use App\Domain\Shared\Money;
use App\Domain\Shared\TrashableStatus;
use App\Domain\Vehicle\Vehicle;
use App\Infrastructure\Persistence\PostgresVehicleRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;

/** Isolado por transação (rollback no tearDown), igual PostgresDealershipRepositoryTest. */
#[Group('integration')]
final class PostgresVehicleRepositoryTest extends TestCase
{
    private \PDO $pdo;
    private PostgresVehicleRepository $repository;

    protected function setUp(): void
    {
        $connection = TestDatabase::connect();
        $this->pdo = $connection->pdo();

        $this->pdo->beginTransaction();
        $this->repository = new PostgresVehicleRepository($connection);
    }

    protected function tearDown(): void
    {
        $this->pdo->rollBack();
    }

    #[Test]
    public function insere_e_encontra_por_id(): void
    {
        $dealership = $this->insertDealership($this->insertSellerUser());
        $vehicle = $this->registerFixture($dealership);

        $this->repository->insert($vehicle);
        $found = $this->repository->findById($vehicle->id);

        $this->assertNotNull($found);
        $this->assertSame($vehicle->id, $found->id);
        $this->assertSame($dealership, $found->dealershipId);
        $this->assertSame('Chevrolet', $found->brand);
        $this->assertSame(2023, $found->year);
        $this->assertSame(TrashableStatus::Active, $found->trash->status);
    }

    #[Test]
    public function preco_faz_a_ida_e_volta_pelo_banco_sem_perder_centavo(): void
    {
        $dealership = $this->insertDealership($this->insertSellerUser());
        $vehicle = $this->registerFixture($dealership, price: new Money(8990099));

        $this->repository->insert($vehicle);
        $found = $this->repository->findById($vehicle->id);

        $this->assertNotNull($found);
        $this->assertSame(8990099, $found->price->cents);
        $this->assertSame('89900.99', $found->price->toDecimal());
    }

    #[Test]
    public function update_persiste_as_alteracoes(): void
    {
        $dealership = $this->insertDealership($this->insertSellerUser());
        $vehicle = $this->registerFixture($dealership);
        $this->repository->insert($vehicle);

        $this->repository->update($vehicle->withDetails('Fiat', 'Argo', null, 2024, new Money(7550000), 'Único dono'));

        $found = $this->repository->findById($vehicle->id);
        $this->assertNotNull($found);
        $this->assertSame('Fiat', $found->brand);
        $this->assertNull($found->version);
        $this->assertSame('Único dono', $found->description);
    }

    #[Test]
    public function find_by_owner_traz_so_os_veiculos_das_concessionarias_daquele_dono(): void
    {
        $owner = $this->insertSellerUser();
        $otherOwner = $this->insertSellerUser();
        $mine = $this->registerFixture($this->insertDealership($owner));
        $notMine = $this->registerFixture($this->insertDealership($otherOwner));
        $this->repository->insert($mine);
        $this->repository->insert($notMine);

        $found = $this->repository->findByOwner($owner, 10, 0);

        $this->assertSame([$mine->id], array_map(static fn (Vehicle $v): string => $v->id, $found));
        $this->assertSame(1, $this->repository->countByOwner($owner));
    }

    #[Test]
    public function find_by_owner_respeita_limit_e_offset(): void
    {
        $owner = $this->insertSellerUser();
        $dealership = $this->insertDealership($owner);
        $first = $this->registerFixture($dealership, brand: 'Chevrolet');
        $second = $this->registerFixture($dealership, brand: 'Fiat');
        $this->repository->insert($first);
        $this->repository->insert($second);

        $page = $this->repository->findByOwner($owner, 1, 1);

        $this->assertCount(1, $page);
        $this->assertSame($second->id, $page[0]->id);
    }

    #[Test]
    public function find_by_dealership_traz_so_os_daquela_concessionaria(): void
    {
        $owner = $this->insertSellerUser();
        $dealership = $this->insertDealership($owner);
        $otherDealership = $this->insertDealership($owner);
        $here = $this->registerFixture($dealership);
        $there = $this->registerFixture($otherDealership);
        $this->repository->insert($here);
        $this->repository->insert($there);

        $found = $this->repository->findByDealership($dealership, 10, 0);

        $this->assertSame([$here->id], array_map(static fn (Vehicle $v): string => $v->id, $found));
        $this->assertSame(1, $this->repository->countByDealership($dealership));
    }

    #[Test]
    public function listagem_ignora_veiculo_deletado(): void
    {
        $owner = $this->insertSellerUser();
        $vehicle = $this->registerFixture($this->insertDealership($owner));
        $this->repository->insert($vehicle);
        $this->repository->purge($vehicle->anonymized());

        $this->assertSame([], $this->repository->findByOwner($owner, 10, 0));
        $this->assertSame(0, $this->repository->countByOwner($owner));
    }

    #[Test]
    public function trash_move_pra_status_trashed_e_seta_trashed_at(): void
    {
        $vehicle = $this->registerFixture($this->insertDealership($this->insertSellerUser()));
        $this->repository->insert($vehicle);

        $this->repository->trash($vehicle->id);

        $found = $this->repository->findById($vehicle->id);
        $this->assertNotNull($found);
        $this->assertSame(TrashableStatus::Trashed, $found->trash->status);
        $this->assertInstanceOf(\DateTimeImmutable::class, $found->trash->trashedAt);
    }

    #[Test]
    public function restore_volta_status_active_e_limpa_trashed_at(): void
    {
        $vehicle = $this->registerFixture($this->insertDealership($this->insertSellerUser()));
        $this->repository->insert($vehicle);
        $this->repository->trash($vehicle->id);

        $this->repository->restore($vehicle->id);

        $found = $this->repository->findById($vehicle->id);
        $this->assertNotNull($found);
        $this->assertSame(TrashableStatus::Active, $found->trash->status);
        $this->assertNull($found->trash->trashedAt);
    }

    #[Test]
    public function find_trashed_so_traz_trashed_ainda_nao_anonimizado(): void
    {
        $dealership = $this->insertDealership($this->insertSellerUser());
        $trashed = $this->registerFixture($dealership);
        $purged = $this->registerFixture($dealership);
        $this->repository->insert($trashed);
        $this->repository->insert($purged);
        $this->repository->trash($trashed->id);
        $this->repository->trash($purged->id);
        $stored = $this->repository->findById($purged->id);
        $this->assertNotNull($stored);
        $this->repository->purge($stored->anonymized());

        $ids = array_map(static fn (mixed $v): string => $v instanceof Vehicle ? $v->id : '', $this->repository->findTrashed());

        $this->assertContains($trashed->id, $ids);
        $this->assertNotContains($purged->id, $ids);
    }

    #[Test]
    public function trash_all_in_dealership_so_afeta_os_ativos_e_marca_por_cascata(): void
    {
        $dealership = $this->insertDealership($this->insertSellerUser());
        $active = $this->registerFixture($dealership);
        $alreadyTrashed = $this->registerFixture($dealership);
        $this->repository->insert($active);
        $this->repository->insert($alreadyTrashed);
        $this->repository->trash($alreadyTrashed->id);

        $this->repository->trashAllInDealership($dealership);

        $cascaded = $this->repository->findById($active->id);
        $manual = $this->repository->findById($alreadyTrashed->id);
        $this->assertNotNull($cascaded);
        $this->assertNotNull($manual);
        $this->assertSame(TrashableStatus::Trashed, $cascaded->trash->status);
        $this->assertTrue($cascaded->trashedByDealershipTrash);
        $this->assertFalse($manual->trashedByDealershipTrash);
    }

    #[Test]
    public function restore_auto_trashed_in_dealership_so_restaura_quem_caiu_por_cascata(): void
    {
        $dealership = $this->insertDealership($this->insertSellerUser());
        $cascaded = $this->registerFixture($dealership);
        $manual = $this->registerFixture($dealership);
        $this->repository->insert($cascaded);
        $this->repository->insert($manual);
        $this->repository->trash($manual->id);
        $this->repository->trashAllInDealership($dealership);

        $this->repository->restoreAutoTrashedInDealership($dealership);

        $restored = $this->repository->findById($cascaded->id);
        $stillTrashed = $this->repository->findById($manual->id);
        $this->assertNotNull($restored);
        $this->assertNotNull($stillTrashed);
        $this->assertSame(TrashableStatus::Active, $restored->trash->status);
        $this->assertSame(TrashableStatus::Trashed, $stillTrashed->trash->status);
    }

    #[Test]
    public function cascata_por_dono_alcanca_todas_as_concessionarias_dele(): void
    {
        $owner = $this->insertSellerUser();
        $first = $this->registerFixture($this->insertDealership($owner));
        $second = $this->registerFixture($this->insertDealership($owner));
        $this->repository->insert($first);
        $this->repository->insert($second);

        $this->repository->trashAllOwnedByUser($owner);
        $trashedCount = count(array_filter(
            [$this->repository->findById($first->id), $this->repository->findById($second->id)],
            static fn (?Vehicle $v): bool => $v?->trash->status === TrashableStatus::Trashed,
        ));
        $this->repository->restoreAutoTrashedOwnedByUser($owner);

        $this->assertSame(2, $trashedCount);
        $this->assertSame(TrashableStatus::Active, $this->repository->findById($first->id)?->trash->status);
        $this->assertSame(TrashableStatus::Active, $this->repository->findById($second->id)?->trash->status);
    }

    private function registerFixture(string $dealershipId, string $brand = 'Chevrolet', ?Money $price = null): Vehicle
    {
        return Vehicle::register(
            dealershipId: $dealershipId,
            brand: $brand,
            model: 'Onix',
            version: 'LTZ 1.0 Turbo',
            year: 2023,
            price: $price ?? new Money(8990000),
        );
    }

    private function insertSellerUser(): string
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            INSERT INTO users (name, email, password, role)
            VALUES ('Seller Test', :email, 'hash', 'seller')
            RETURNING id
            SQL);
        $statement->execute(['email' => 'seller-' . bin2hex(random_bytes(8)) . '@example.com']);

        return (string) $statement->fetchColumn();
    }

    private function insertDealership(string $ownerUserId): string
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            INSERT INTO dealerships (owner_user_id, name, slug, zip_code, address, number, neighborhood, city, state)
            VALUES (:owner_user_id, 'Auto Center', :slug, '01000-000', 'Rua Antiga', '10', 'Bairro', 'Cidade', 'SP')
            RETURNING id
            SQL);
        $statement->execute(['owner_user_id' => $ownerUserId, 'slug' => 'auto-center-' . bin2hex(random_bytes(4))]);

        return (string) $statement->fetchColumn();
    }
}
