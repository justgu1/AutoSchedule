<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Users;

use App\Domain\Users\User;
use App\Domain\Users\UserRole;
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
        $this->pdo = (new PostgresConnection(
            driver: getenv('DB_DRIVER') ?: 'pgsql',
            host: getenv('DB_HOST') ?: '127.0.0.1',
            port: (int) (getenv('DB_PORT') ?: 5432),
            database: getenv('DB_DATABASE') ?: 'autoschedule',
            username: getenv('DB_USERNAME') ?: 'pgsql',
            password: getenv('DB_PASSWORD') ?: 'password',
        ))->pdo();

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
    public function find_all_inclui_usuario_recem_criado_mas_nao_o_soft_deletado(): void
    {
        $active = User::register('Ada Lovelace', 'ada@example.com', null, 'secret', UserRole::Customer);
        $deleted = User::register('Charles Babbage', 'charles@example.com', null, 'secret', UserRole::Customer);
        $this->repository->insert($active);
        $this->repository->insert($deleted);
        $this->repository->anonymizeAndSoftDelete($deleted->id);

        $ids = array_map(static fn (User $user): string => $user->id, $this->repository->findAll());

        $this->assertContains($active->id, $ids);
        $this->assertNotContains($deleted->id, $ids);
    }

    #[Test]
    public function count_by_role_conta_so_usuario_ativo_do_role_informado(): void
    {
        $before = $this->repository->countByRole(UserRole::Seller);

        $this->repository->insert(User::register('Ada', 'ada-seller@example.com', null, 'secret', UserRole::Seller));

        $this->assertSame($before + 1, $this->repository->countByRole(UserRole::Seller));
    }
}
