<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Scheduler;

use App\Domain\Audit\AuditEvent;
use App\Domain\Dealership\Dealership;
use App\Domain\Shared\Address;
use App\Domain\Shared\TrashableStatus;
use App\Domain\Shared\Uf;
use App\Infrastructure\Persistence\PostgresDealershipRepository;
use App\Infrastructure\Scheduler\PurgeTrashedEntitiesTask;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeAuditLogger;
use Tests\Support\TestDatabase;

#[Group('integration')]
final class PurgeTrashedDealershipsTaskTest extends TestCase
{
    private \PDO $pdo;
    private PostgresDealershipRepository $repository;
    private FakeAuditLogger $audit;

    private PurgeTrashedEntitiesTask $task;

    protected function setUp(): void
    {
        $connection = TestDatabase::connect();
        $this->pdo = $connection->pdo();

        $this->pdo->beginTransaction();
        $this->repository = new PostgresDealershipRepository($connection);
        $this->audit = new FakeAuditLogger();
        $this->task = new PurgeTrashedEntitiesTask(
            name: 'purge-trashed-dealerships',
            dueIntervalSeconds: 86400,
            repository: $this->repository,
            audit: $this->audit,
            event: AuditEvent::DealershipPurged,
            auditableType: 'Dealership',
        );
    }

    protected function tearDown(): void
    {
        $this->pdo->rollBack();
    }

    #[Test]
    public function anonimiza_so_quem_esta_trashed_ha_mais_de_30_dias(): void
    {
        $owner = $this->insertSellerUser();
        $longTrashed = $this->registerFixture($owner, 'Long');
        $recentlyTrashed = $this->registerFixture($owner, 'Recent');
        $this->repository->insert($longTrashed);
        $this->repository->insert($recentlyTrashed);
        $this->repository->trash($longTrashed->id, false);
        $this->repository->trash($recentlyTrashed->id, false);
        $this->pdo->prepare("UPDATE dealerships SET trashed_at = now() - interval '31 days' WHERE id = ?")->execute([$longTrashed->id]);

        $this->task->run();

        $purged = $this->repository->findById($longTrashed->id);
        $this->assertNotNull($purged);
        $this->assertSame(TrashableStatus::Deleted, $purged->trash->status);
        $this->assertNotSame('Long', $purged->name);

        $stillTrashed = $this->repository->findById($recentlyTrashed->id);
        $this->assertNotNull($stillTrashed);
        $this->assertSame(TrashableStatus::Trashed, $stillTrashed->trash->status);
    }

    #[Test]
    public function audita_cada_concessionaria_purgada(): void
    {
        $owner = $this->insertSellerUser();
        $longTrashed = $this->registerFixture($owner, 'Long');
        $this->repository->insert($longTrashed);
        $this->repository->trash($longTrashed->id, false);
        $this->pdo->prepare("UPDATE dealerships SET trashed_at = now() - interval '31 days' WHERE id = ?")->execute([$longTrashed->id]);

        $this->task->run();

        $this->assertSame([AuditEvent::DealershipPurged], $this->audit->events);
        $this->assertSame($longTrashed->id, $this->audit->calls[0]['targetUserId']);
        $this->assertSame('Dealership', $this->audit->calls[0]['auditableType']);
    }

    #[Test]
    public function nao_e_um_no_op_falha_quando_nao_ha_ninguem_elegivel(): void
    {
        $this->task->run();

        $this->assertSame([], $this->audit->events);
    }

    private function registerFixture(string $ownerUserId, string $name): Dealership
    {
        return Dealership::register(
            ownerUserId: $ownerUserId,
            name: $name,
            address: new Address('01000-000', 'Rua Antiga', '10', null, 'Bairro', 'Cidade', Uf::SP),
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
}
