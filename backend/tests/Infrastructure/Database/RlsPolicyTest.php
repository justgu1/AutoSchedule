<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Database;

use App\Domain\Users\User;
use App\Domain\Users\UserRole;
use App\Infrastructure\Database\PostgresConnection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Teste de integração: valida as policies de RLS de `users` de verdade,
 * conectando como autoschedule_app (a role admin/pgsql é superuser e sempre
 * ignora RLS, não serve pra esse teste). Como são DUAS conexões/sessões
 * diferentes, os dados de fixture precisam ser commitados de verdade pela
 * conexão admin (senão a sessão autoschedule_app nunca os enxerga) --
 * limpeza no tearDown é um DELETE, não rollback de transação.
 */
final class RlsPolicyTest extends TestCase
{
    private \PDO $admin;
    private \PDO $rls;
    private User $customer;
    private User $otherCustomer;

    protected function setUp(): void
    {
        $this->admin = (new PostgresConnection(
            driver: getenv('DB_DRIVER') ?: 'pgsql',
            host: getenv('DB_HOST') ?: '127.0.0.1',
            port: (int) (getenv('DB_PORT') ?: 5432),
            database: getenv('DB_DATABASE') ?: 'autoschedule',
            username: getenv('DB_USERNAME') ?: 'pgsql',
            password: getenv('DB_PASSWORD') ?: 'password',
        ))->pdo();

        $this->customer = User::register('Customer RLS', 'rls-customer@example.com', null, 'secret', UserRole::Customer);
        $this->otherCustomer = User::register('Other Customer RLS', 'rls-other@example.com', null, 'secret', UserRole::Customer);
        $this->insertUser($this->customer);
        $this->insertUser($this->otherCustomer);

        $this->rls = (new PostgresConnection(
            driver: getenv('DB_DRIVER') ?: 'pgsql',
            host: getenv('DB_HOST') ?: '127.0.0.1',
            port: (int) (getenv('DB_PORT') ?: 5432),
            database: getenv('DB_DATABASE') ?: 'autoschedule',
            username: getenv('DB_APP_USERNAME') ?: 'autoschedule_app',
            password: getenv('DB_APP_PASSWORD') ?: 'changeme',
        ))->pdo();
    }

    protected function tearDown(): void
    {
        $statement = $this->admin->prepare('DELETE FROM users WHERE id IN (?, ?)');
        $statement->execute([$this->customer->id, $this->otherCustomer->id]);
    }

    #[Test]
    public function customer_so_enxerga_a_propria_linha(): void
    {
        $this->rls->beginTransaction();
        $this->setContext($this->customer->id, 'customer');
        $ids = array_column($this->rls->query('SELECT id FROM users')->fetchAll(), 'id');
        $this->rls->rollBack();

        $this->assertSame([$this->customer->id], $ids);
    }

    #[Test]
    public function admin_enxerga_qualquer_linha(): void
    {
        $this->rls->beginTransaction();
        $this->setContext($this->customer->id, 'admin');
        $ids = array_column($this->rls->query('SELECT id FROM users')->fetchAll(), 'id');
        $this->rls->rollBack();

        $this->assertContains($this->customer->id, $ids);
        $this->assertContains($this->otherCustomer->id, $ids);
    }

    #[Test]
    public function contexto_de_servico_enxerga_qualquer_linha(): void
    {
        $this->rls->beginTransaction();
        $this->rls->exec("SET LOCAL app.is_service_context = 'true'");
        $ids = array_column($this->rls->query('SELECT id FROM users')->fetchAll(), 'id');
        $this->rls->rollBack();

        $this->assertContains($this->customer->id, $ids);
        $this->assertContains($this->otherCustomer->id, $ids);
    }

    #[Test]
    public function sem_contexto_setado_nenhuma_linha_e_retornada(): void
    {
        $this->rls->beginTransaction();
        $ids = array_column($this->rls->query('SELECT id FROM users')->fetchAll(), 'id');
        $this->rls->rollBack();

        $this->assertSame([], $ids);
    }

    #[Test]
    public function contexto_de_servico_consegue_inserir(): void
    {
        $newUser = User::register('Registered RLS', 'rls-registered@example.com', null, 'secret', UserRole::Customer);

        $this->rls->beginTransaction();
        $this->rls->exec("SET LOCAL app.is_service_context = 'true'");
        $this->insertUser($newUser, $this->rls);
        $this->rls->rollBack();

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function sem_contexto_setado_insert_falha(): void
    {
        $newUser = User::register('Blocked RLS', 'rls-blocked@example.com', null, 'secret', UserRole::Customer);

        $this->rls->beginTransaction();

        try {
            $this->expectException(\PDOException::class);
            $this->insertUser($newUser, $this->rls);
        } finally {
            $this->rls->rollBack();
        }
    }

    private function setContext(string $userId, string $role): void
    {
        $this->rls->exec('SET LOCAL app.current_user_id = ' . $this->rls->quote($userId));
        $this->rls->exec('SET LOCAL app.current_user_role = ' . $this->rls->quote($role));
    }

    private function insertUser(User $user, ?\PDO $pdo = null): void
    {
        $statement = ($pdo ?? $this->admin)->prepare(<<<'SQL'
            INSERT INTO users (id, name, email, phone, password, role, password_set_at, created_at, updated_at)
            VALUES (:id, :name, :email, :phone, :password, :role, :password_set_at, :created_at, :updated_at)
            SQL);

        $statement->execute([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'password' => $user->passwordHash,
            'role' => $user->role->value,
            'password_set_at' => $user->passwordSetAt?->format(DATE_ATOM),
            'created_at' => $user->createdAt->format(DATE_ATOM),
            'updated_at' => $user->updatedAt->format(DATE_ATOM),
        ]);
    }
}
