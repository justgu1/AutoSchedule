<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Dealerships;

use App\Domain\Dealerships\Dealership;
use App\Domain\Dealerships\DealershipImage;
use App\Domain\Shared\TrashableStatus;
use App\Infrastructure\Database\PostgresConnection;
use App\Infrastructure\Dealerships\PostgresDealershipRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Teste de integração: conecta no Postgres real do docker-compose. Isolado
 * por transação (rollback no tearDown), igual PostgresUserRepositoryTest.
 */
final class PostgresDealershipRepositoryTest extends TestCase
{
    private \PDO $pdo;
    private PostgresDealershipRepository $repository;

    protected function setUp(): void
    {
        $this->pdo = new PostgresConnection(
            driver: getenv('DB_DRIVER') ?: 'pgsql',
            host: getenv('DB_HOST') ?: '127.0.0.1',
            port: (int) (getenv('DB_PORT') ?: 5432),
            database: getenv('DB_DATABASE') ?: 'autoschedule',
            username: getenv('DB_USERNAME') ?: 'pgsql',
            password: getenv('DB_PASSWORD') ?: 'password',
        )->pdo();

        $this->pdo->beginTransaction();
        $this->repository = new PostgresDealershipRepository($this->pdo);
    }

    protected function tearDown(): void
    {
        $this->pdo->rollBack();
    }

    #[Test]
    public function insere_e_encontra_por_id(): void
    {
        $owner = $this->insertSellerUser();
        $dealership = $this->registerFixture($owner);

        $this->repository->insert($dealership);
        $found = $this->repository->findById($dealership->id);

        $this->assertNotNull($found);
        $this->assertSame($dealership->id, $found->id);
        $this->assertSame($owner, $found->ownerUserId);
        $this->assertSame('Auto Center', $found->name);
        $this->assertSame(TrashableStatus::Active, $found->status);
    }

    #[Test]
    public function update_persiste_as_alteracoes(): void
    {
        $owner = $this->insertSellerUser();
        $dealership = $this->registerFixture($owner);
        $this->repository->insert($dealership);

        $updated = $dealership->withProfile(
            name: 'Novo Nome',
            zipCode: $dealership->zipCode,
            address: $dealership->address,
            number: $dealership->number,
            complement: $dealership->complement,
            neighborhood: $dealership->neighborhood,
            city: $dealership->city,
            state: $dealership->state,
            phone: $dealership->phone,
            latitude: $dealership->latitude,
            longitude: $dealership->longitude,
            googlePlaceId: $dealership->googlePlaceId,
        );
        $this->repository->update($updated);

        $found = $this->repository->findById($dealership->id);
        $this->assertNotNull($found);
        $this->assertSame('Novo Nome', $found->name);
    }

    #[Test]
    public function find_by_owner_traz_so_as_concessionarias_daquele_dono_nao_deletadas(): void
    {
        $owner = $this->insertSellerUser();
        $otherOwner = $this->insertSellerUser();
        $mine = $this->registerFixture($owner, name: 'Minha');
        $alsoMine = $this->registerFixture($owner, name: 'Também Minha');
        $notMine = $this->registerFixture($otherOwner, name: 'Não é Minha');
        $this->repository->insert($mine);
        $this->repository->insert($alsoMine);
        $this->repository->insert($notMine);

        $found = $this->repository->findByOwner($owner, 10, 0);

        $ids = array_map(static fn (Dealership $d): string => $d->id, $found);
        $this->assertContains($mine->id, $ids);
        $this->assertContains($alsoMine->id, $ids);
        $this->assertNotContains($notMine->id, $ids);
    }

    #[Test]
    public function find_by_owner_respeita_limit_e_offset(): void
    {
        $owner = $this->insertSellerUser();
        $first = $this->registerFixture($owner, name: 'Primeira');
        $second = $this->registerFixture($owner, name: 'Segunda');
        $this->repository->insert($first);
        $this->repository->insert($second);

        $firstPage = $this->repository->findByOwner($owner, 1, 0);
        $secondPage = $this->repository->findByOwner($owner, 1, 1);

        $this->assertCount(1, $firstPage);
        $this->assertSame($first->id, $firstPage[0]->id);
        $this->assertCount(1, $secondPage);
        $this->assertSame($second->id, $secondPage[0]->id);
    }

    #[Test]
    public function count_by_owner_so_conta_as_do_dono_nao_deletadas(): void
    {
        $owner = $this->insertSellerUser();
        $otherOwner = $this->insertSellerUser();
        $this->repository->insert($this->registerFixture($owner, name: 'Uma'));
        $this->repository->insert($this->registerFixture($owner, name: 'Outra'));
        $this->repository->insert($this->registerFixture($otherOwner, name: 'De outro dono'));

        $this->assertSame(2, $this->repository->countByOwner($owner));
    }

    #[Test]
    public function trash_move_pra_status_trashed_e_seta_trashed_at(): void
    {
        $owner = $this->insertSellerUser();
        $dealership = $this->registerFixture($owner);
        $this->repository->insert($dealership);

        $this->repository->trash($dealership->id, byOwnerDeactivation: true);

        $found = $this->repository->findById($dealership->id);
        $this->assertNotNull($found);
        $this->assertSame(TrashableStatus::Trashed, $found->status);
        $this->assertTrue($found->trashedByOwnerDeactivation);
        $this->assertNotNull($found->trashedAt);
    }

    #[Test]
    public function restore_volta_status_active_e_limpa_trashed_at(): void
    {
        $owner = $this->insertSellerUser();
        $dealership = $this->registerFixture($owner);
        $this->repository->insert($dealership);
        $this->repository->trash($dealership->id, byOwnerDeactivation: false);

        $this->repository->restore($dealership->id);

        $found = $this->repository->findById($dealership->id);
        $this->assertNotNull($found);
        $this->assertSame(TrashableStatus::Active, $found->status);
        $this->assertNull($found->trashedAt);
        $this->assertFalse($found->trashedByOwnerDeactivation);
    }

    #[Test]
    public function find_purge_eligible_so_traz_trashed_ha_mais_de_grace_days_e_ainda_nao_anonimizado(): void
    {
        $owner = $this->insertSellerUser();
        $recentlyTrashed = $this->registerFixture($owner, name: 'Recente');
        $longTrashed = $this->registerFixture($owner, name: 'Antiga');
        $this->repository->insert($recentlyTrashed);
        $this->repository->insert($longTrashed);
        $this->repository->trash($recentlyTrashed->id, false);
        $this->repository->trash($longTrashed->id, false);
        $this->pdo->prepare("UPDATE dealerships SET trashed_at = now() - interval '31 days' WHERE id = ?")->execute([$longTrashed->id]);

        $eligible = $this->repository->findPurgeEligible(30, new \DateTimeImmutable());

        $this->assertCount(1, $eligible);
        $this->assertSame($longTrashed->id, $eligible[0]->id);
    }

    #[Test]
    public function trash_all_owned_by_so_afeta_as_ativas_e_marca_por_desativacao_do_dono(): void
    {
        $owner = $this->insertSellerUser();
        $active = $this->registerFixture($owner, name: 'Ativa');
        $alreadyTrashed = $this->registerFixture($owner, name: 'Já na lixeira');
        $this->repository->insert($active);
        $this->repository->insert($alreadyTrashed);
        $this->repository->trash($alreadyTrashed->id, byOwnerDeactivation: false);

        $this->repository->trashAllOwnedBy($owner);

        $foundActive = $this->repository->findById($active->id);
        $this->assertNotNull($foundActive);
        $this->assertSame(TrashableStatus::Trashed, $foundActive->status);
        $this->assertTrue($foundActive->trashedByOwnerDeactivation);
    }

    #[Test]
    public function restore_auto_trashed_owned_by_so_restaura_quem_foi_trashed_por_causa_do_dono(): void
    {
        $owner = $this->insertSellerUser();
        $byOwnerDeactivation = $this->registerFixture($owner, name: 'Cascata');
        $manual = $this->registerFixture($owner, name: 'Manual');
        $this->repository->insert($byOwnerDeactivation);
        $this->repository->insert($manual);
        $this->repository->trash($byOwnerDeactivation->id, byOwnerDeactivation: true);
        $this->repository->trash($manual->id, byOwnerDeactivation: false);

        $this->repository->restoreAutoTrashedOwnedBy($owner);

        $foundCascade = $this->repository->findById($byOwnerDeactivation->id);
        $foundManual = $this->repository->findById($manual->id);
        $this->assertNotNull($foundCascade);
        $this->assertNotNull($foundManual);
        $this->assertSame(TrashableStatus::Active, $foundCascade->status);
        $this->assertSame(TrashableStatus::Trashed, $foundManual->status);
    }

    #[Test]
    public function galeria_insere_encontra_e_calcula_a_proxima_posicao(): void
    {
        $owner = $this->insertSellerUser();
        $dealership = $this->registerFixture($owner);
        $this->repository->insert($dealership);
        $file = $this->insertFile();

        $this->assertSame(0, $this->repository->nextImagePosition($dealership->id));

        $image = DealershipImage::register($dealership->id, $file, 0);
        $this->repository->insertImage($image);

        $this->assertSame(1, $this->repository->nextImagePosition($dealership->id));
        $found = $this->repository->findImageById($image->id);
        $this->assertNotNull($found);
        $this->assertSame($dealership->id, $found->dealershipId);

        $images = $this->repository->findImagesByDealership($dealership->id);
        $this->assertCount(1, $images);

        $this->repository->deleteImage($image->id);
        $this->assertNull($this->repository->findImageById($image->id));
    }

    private function registerFixture(string $ownerUserId, string $name = 'Auto Center'): Dealership
    {
        return Dealership::register(
            ownerUserId: $ownerUserId,
            name: $name,
            zipCode: '01000-000',
            address: 'Rua Antiga',
            number: '10',
            complement: null,
            neighborhood: 'Bairro',
            city: 'Cidade',
            state: 'SP',
            phone: '11988888888',
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

    private function insertFile(): string
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            INSERT INTO files (path, original_name, mime_type, size_bytes, checksum)
            VALUES (:path, 'foto.jpg', 'image/jpeg', 123, :checksum)
            RETURNING id
            SQL);
        $checksum = bin2hex(random_bytes(16));
        $statement->execute(['path' => $checksum, 'checksum' => $checksum]);

        return (string) $statement->fetchColumn();
    }
}
