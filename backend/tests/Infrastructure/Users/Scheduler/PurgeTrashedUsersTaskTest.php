<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Users\Scheduler;

use App\Domain\Audit\AuditEvent;
use App\Domain\Users\User;
use App\Domain\Users\UserRole;
use App\Infrastructure\Database\PostgresConnection;
use App\Infrastructure\Scheduler\PurgeTrashedEntitiesTask;
use App\Infrastructure\Users\PostgresUserRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Domain\Auth\FakeAuditLogger;

/** Teste de integração: conecta no Postgres real do docker-compose, igual PostgresUserRepositoryTest. */
final class PurgeTrashedUsersTaskTest extends TestCase
{
    private \PDO $pdo;
    private PostgresUserRepository $repository;
    private FakeAuditLogger $audit;

    /** @var PurgeTrashedEntitiesTask<User> */
    private PurgeTrashedEntitiesTask $task;

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
        $this->repository = new PostgresUserRepository($this->pdo);
        $this->audit = new FakeAuditLogger();
        $repository = $this->repository;
        $this->task = new PurgeTrashedEntitiesTask(
            name: 'purge-trashed-users',
            graceDays: 30,
            dueIntervalSeconds: 86400,
            findEligible: $repository->findPurgeEligible(...),
            purge: static fn (User $user) => $repository->anonymizeAndSoftDelete($user->id),
            identify: static fn (User $user): string => $user->id,
            audit: $this->audit,
            event: AuditEvent::AccountPurged,
            auditableType: 'User',
        );
    }

    protected function tearDown(): void
    {
        $this->pdo->rollBack();
    }

    #[Test]
    public function anonimiza_so_quem_esta_trashed_ha_mais_de_30_dias(): void
    {
        $longTrashed = User::register('Long', 'long@example.com', null, 'secret', UserRole::Customer);
        $recentlyTrashed = User::register('Recent', 'recent@example.com', null, 'secret', UserRole::Customer);
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
        $longTrashed = User::register('Long', 'long@example.com', null, 'secret', UserRole::Customer);
        $this->repository->insert($longTrashed);
        $this->repository->trash($longTrashed->id);
        $this->pdo->prepare("UPDATE users SET deleted_at = now() - interval '31 days' WHERE id = ?")->execute([$longTrashed->id]);

        $this->task->run();

        $this->assertSame([AuditEvent::AccountPurged], $this->audit->events);
        $this->assertSame($longTrashed->id, $this->audit->calls[0]['targetUserId']);
        $this->assertSame('User', $this->audit->calls[0]['auditableType']);
    }

    #[Test]
    public function nao_e_um_no_op_falha_quando_nao_ha_ninguem_elegivel(): void
    {
        $this->task->run();

        $this->assertSame([], $this->audit->events);
    }
}
