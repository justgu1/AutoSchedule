<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Scheduler;

use App\Domain\Audit\AuditableType;
use App\Domain\Audit\AuditEvent;
use App\Domain\Shared\Email;
use App\Domain\User\User;
use App\Domain\User\UserRole;
use App\Infrastructure\Persistence\PdoTransaction;
use App\Infrastructure\Persistence\PostgresUserRepository;
use App\Infrastructure\Scheduler\PurgeTrashedEntitiesTask;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeAuditLogger;
use Tests\Support\TestDatabase;

/** Teste de integração: conecta no Postgres real do docker-compose, igual PostgresUserRepositoryTest. */
#[Group('integration')]
final class PurgeTrashedUsersTaskTest extends TestCase
{
    private \PDO $pdo;
    private PostgresUserRepository $repository;
    private FakeAuditLogger $audit;

    private PurgeTrashedEntitiesTask $task;

    protected function setUp(): void
    {
        $connection = TestDatabase::connect();
        $this->pdo = $connection->pdo();

        $this->pdo->beginTransaction();
        $this->repository = new PostgresUserRepository($connection);
        $this->audit = new FakeAuditLogger();
        $this->task = new PurgeTrashedEntitiesTask(
            name: 'purge-trashed-users',
            dueIntervalSeconds: 86400,
            repository: $this->repository,
            audit: $this->audit,
            event: AuditEvent::AccountPurged,
            transaction: new PdoTransaction($connection),
        );
    }

    protected function tearDown(): void
    {
        $this->pdo->rollBack();
    }

    #[Test]
    public function anonimiza_so_quem_esta_trashed_ha_mais_de_30_dias(): void
    {
        $longTrashed = User::register('Long', new Email('long@example.com'), null, 'secret', UserRole::Customer);
        $recentlyTrashed = User::register('Recent', new Email('recent@example.com'), null, 'secret', UserRole::Customer);
        $this->repository->insert($longTrashed);
        $this->repository->insert($recentlyTrashed);
        $this->repository->trash($longTrashed->id);
        $this->repository->trash($recentlyTrashed->id);
        $this->pdo->prepare("UPDATE users SET deleted_at = now() - interval '31 days' WHERE id = ?")->execute([$longTrashed->id]);

        $this->task->run();

        $purged = $this->repository->findById($longTrashed->id);
        $this->assertNull($purged);
        $stillTrashed = $this->pdo->prepare('SELECT status FROM users WHERE id = ?');
        $stillTrashed->execute([$recentlyTrashed->id]);
        $this->assertSame('trashed', $stillTrashed->fetchColumn());
    }

    #[Test]
    public function audita_cada_conta_purgada(): void
    {
        $longTrashed = User::register('Long', new Email('long@example.com'), null, 'secret', UserRole::Customer);
        $this->repository->insert($longTrashed);
        $this->repository->trash($longTrashed->id);
        $this->pdo->prepare("UPDATE users SET deleted_at = now() - interval '31 days' WHERE id = ?")->execute([$longTrashed->id]);

        $this->task->run();

        $this->assertSame([AuditEvent::AccountPurged], $this->audit->events);
        $this->assertSame($longTrashed->id, $this->audit->entries[0]->auditableId);
        $this->assertSame(AuditableType::User, $this->audit->entries[0]->auditableType());
    }

    #[Test]
    public function nao_e_um_no_op_falha_quando_nao_ha_ninguem_elegivel(): void
    {
        $this->task->run();

        $this->assertSame([], $this->audit->events);
    }
}
