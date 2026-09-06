<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Users;

use App\Domain\Users\User;
use App\Domain\Users\UserRole;
use App\Domain\Users\UserStatus;
use App\Infrastructure\Database\PostgresConnection;
use App\Infrastructure\Users\PostgresUserRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Teste de integração: conecta no Postgres real do docker-compose. Isolado
 * por transação (rollback no tearDown), igual o SeederRunnerTest.
 */
final class PostgresUserRepositoryTest extends TestCase
{
    private \PDO $pdo;
    private PostgresUserRepository $repository;

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
    }

    protected function tearDown(): void
    {
        $this->pdo->rollBack();
    }

    #[Test]
    public function insere_e_encontra_por_id(): void
    {
        $user = User::register('Ada Lovelace', 'ada@example.com', '+55 11 90000-0000', 'secret', UserRole::Seller);
        $this->repository->insert($user);

        $found = $this->repository->findById($user->id);

        $this->assertNotNull($found);
        $this->assertSame($user->id, $found->id);
        $this->assertSame('Ada Lovelace', $found->name);
        $this->assertSame(UserRole::Seller, $found->role);
    }

    #[Test]
    public function insere_e_encontra_por_email(): void
    {
        $user = User::register('Ada Lovelace', 'ada@example.com', '+55 11 90000-0000', 'secret', UserRole::Seller);
        $this->repository->insert($user);

        $found = $this->repository->findByEmail('ada@example.com');

        $this->assertNotNull($found);
        $this->assertSame($user->id, $found->id);
    }

    #[Test]
    public function find_by_id_devolve_null_quando_nao_existe(): void
    {
        $this->assertNull($this->repository->findById('00000000-0000-4000-8000-000000000000'));
    }

    #[Test]
    public function exists_by_email_reflete_o_estado_atual(): void
    {
        $this->assertFalse($this->repository->existsByEmail('ada@example.com'));

        $this->repository->insert(User::register('Ada', 'ada@example.com', null, 'secret', UserRole::Customer));

        $this->assertTrue($this->repository->existsByEmail('ada@example.com'));
    }

    #[Test]
    public function update_persiste_as_alteracoes(): void
    {
        $user = User::register('Ada', 'ada@example.com', null, 'secret', UserRole::Customer);
        $this->repository->insert($user);

        $updated = new User(
            id: $user->id,
            name: 'Ada Byron',
            email: $user->email,
            phone: '+55 11 91111-1111',
            passwordHash: $user->passwordHash,
            role: $user->role,
            passwordSetAt: $user->passwordSetAt,
            emailVerifiedAt: new \DateTimeImmutable(),
            createdAt: $user->createdAt,
            updatedAt: new \DateTimeImmutable(),
            deletedAt: null,
            status: $user->status,
            anonymizedAt: $user->anonymizedAt,
        );
        $this->repository->update($updated);

        $fetched = $this->repository->findById($user->id);

        $this->assertSame('Ada Byron', $fetched->name);
        $this->assertSame('+55 11 91111-1111', $fetched->phone);
        $this->assertNotNull($fetched->emailVerifiedAt);
    }

    #[Test]
    public function anonymize_and_soft_delete_some_das_buscas(): void
    {
        $user = User::register('Ada Lovelace', 'ada@example.com', '+55 11 90000-0000', 'secret', UserRole::Customer);
        $this->repository->insert($user);

        $this->repository->anonymizeAndSoftDelete($user->id);

        $this->assertNull($this->repository->findById($user->id));
        $this->assertFalse($this->repository->existsByEmail('ada@example.com'));
    }

    #[Test]
    public function anonymize_and_soft_delete_escruba_a_pii_na_linha_persistida(): void
    {
        $user = User::register('Ada Lovelace', 'ada@example.com', '+55 11 90000-0000', 'secret', UserRole::Customer);
        $this->repository->insert($user);

        $this->repository->anonymizeAndSoftDelete($user->id);

        $statement = $this->pdo->prepare('SELECT name, email, phone, deleted_at FROM users WHERE id = ?');
        $statement->execute([$user->id]);
        $row = $statement->fetch();

        $this->assertNotFalse($row);
        $this->assertNotSame('Ada Lovelace', $row['name']);
        $this->assertNotSame('ada@example.com', $row['email']);
        $this->assertNull($row['phone']);
        $this->assertNotNull($row['deleted_at']);
    }

    #[Test]
    public function anonymize_and_soft_delete_e_um_no_op_quando_usuario_nao_existe(): void
    {
        $this->repository->anonymizeAndSoftDelete('00000000-0000-4000-8000-000000000000');

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function trash_move_pra_status_trashed_e_seta_deleted_at_sem_apagar_pii(): void
    {
        $user = User::register('Ada Lovelace', 'ada@example.com', null, 'secret', UserRole::Customer);
        $this->repository->insert($user);

        $this->repository->trash($user->id);

        $statement = $this->pdo->prepare('SELECT name, email, status, deleted_at FROM users WHERE id = ?');
        $statement->execute([$user->id]);
        $row = $statement->fetch();

        $this->assertSame('Ada Lovelace', $row['name']);
        $this->assertSame('ada@example.com', $row['email']);
        $this->assertSame('trashed', $row['status']);
        $this->assertNotNull($row['deleted_at']);
    }

    #[Test]
    public function trashed_ainda_e_encontrado_por_email_e_bloqueia_reuso_do_email(): void
    {
        $user = User::register('Ada Lovelace', 'ada@example.com', null, 'secret', UserRole::Customer);
        $this->repository->insert($user);

        $this->repository->trash($user->id);

        $found = $this->repository->findByEmail('ada@example.com');
        $this->assertNotNull($found);
        $this->assertSame(UserStatus::Trashed, $found->status);
        $this->assertTrue($this->repository->existsByEmail('ada@example.com'));
    }

    #[Test]
    public function restore_volta_status_active_e_limpa_deleted_at(): void
    {
        $user = User::register('Ada Lovelace', 'ada@example.com', null, 'secret', UserRole::Customer);
        $this->repository->insert($user);
        $this->repository->trash($user->id);

        $this->repository->restore($user->id);

        $restored = $this->repository->findById($user->id);
        assert($restored instanceof \App\Domain\Users\User);
        $this->assertSame(UserStatus::Active, $restored->status);
        $this->assertNull($restored->deletedAt);
    }

    #[Test]
    public function find_purge_eligible_so_traz_trashed_ha_mais_de_grace_days_e_ainda_nao_anonimizado(): void
    {
        $now = new \DateTimeImmutable();
        $recentlyTrashed = User::register('Recent', 'recent@example.com', null, 'secret', UserRole::Customer);
        $longTrashed = User::register('Long', 'long@example.com', null, 'secret', UserRole::Customer);
        $active = User::register('Active', 'active@example.com', null, 'secret', UserRole::Customer);
        $this->repository->insert($recentlyTrashed);
        $this->repository->insert($longTrashed);
        $this->repository->insert($active);
        $this->repository->trash($recentlyTrashed->id);
        $this->repository->trash($longTrashed->id);
        // trash() usa now() do banco -- ajusta deleted_at pra simular 31 dias atrás.
        $this->pdo->prepare("UPDATE users SET deleted_at = now() - interval '31 days' WHERE id = ?")->execute([$longTrashed->id]);

        $eligible = $this->repository->findPurgeEligible(30, $now);

        $this->assertCount(1, $eligible);
        $this->assertSame($longTrashed->id, $eligible[0]->id);
    }

    #[Test]
    public function find_page_inclui_usuario_recem_criado_mas_nao_o_soft_deletado(): void
    {
        $active = User::register('Ada Lovelace', 'ada@example.com', null, 'secret', UserRole::Customer);
        $deleted = User::register('Charles Babbage', 'charles@example.com', null, 'secret', UserRole::Customer);
        $this->repository->insert($active);
        $this->repository->insert($deleted);
        $this->repository->anonymizeAndSoftDelete($deleted->id);

        $ids = array_map(static fn (User $user): string => $user->id, $this->repository->findPage(1000, 0));

        $this->assertContains($active->id, $ids);
        $this->assertNotContains($deleted->id, $ids);
    }

    #[Test]
    public function find_page_respeita_limit_e_offset(): void
    {
        $first = User::register('First', 'first@example.com', null, 'secret', UserRole::Customer);
        $second = User::register('Second', 'second@example.com', null, 'secret', UserRole::Customer);
        $this->repository->insert($first);
        $this->repository->insert($second);

        $page = $this->repository->findPage(1, 0);

        $this->assertCount(1, $page);
    }

    #[Test]
    public function count_conta_todo_usuario_nao_deletado(): void
    {
        $before = $this->repository->count();

        $this->repository->insert(User::register('Ada', 'ada-count@example.com', null, 'secret', UserRole::Customer));

        $this->assertSame($before + 1, $this->repository->count());
    }

    #[Test]
    public function count_by_role_conta_so_usuario_ativo_do_role_informado(): void
    {
        $before = $this->repository->countByRole(UserRole::Seller);

        $this->repository->insert(User::register('Ada', 'ada-seller@example.com', null, 'secret', UserRole::Seller));

        $this->assertSame($before + 1, $this->repository->countByRole(UserRole::Seller));
    }

    #[Test]
    public function count_by_role_nao_conta_admin_trashed(): void
    {
        $admin = User::register('Ada', 'ada-admin@example.com', null, 'secret', UserRole::Admin);
        $this->repository->insert($admin);
        $before = $this->repository->countByRole(UserRole::Admin);

        $this->repository->trash($admin->id);

        $this->assertSame($before - 1, $this->repository->countByRole(UserRole::Admin));
    }
}
