<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Database;

use App\Infrastructure\Database\PostgresConnection;
use App\Infrastructure\Database\SeederRunner;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Teste de integração: conecta no Postgres real do docker-compose e roda os
 * seeders reais de backend/database/seeders/. Depende da tabela `users`
 * (migration da PR anterior) já existir. Isolado por transação (rollback no
 * tearDown), igual o MigrationRunnerTest.
 */
final class SeederRunnerTest extends TestCase
{
    private const string ADMIN_EMAIL = 'admin@autoschedule.local';

    private \PDO $pdo;
    private SeederRunner $runner;

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

        // Estado real do banco de dev pode já ter o admin seedado (via
        // `make seed`). Zera dentro da própria transação, some no rollback.
        $statement = $this->pdo->prepare('DELETE FROM users WHERE email = ?');
        $statement->execute([self::ADMIN_EMAIL]);

        $this->runner = new SeederRunner($this->pdo, dirname(__DIR__, 3) . '/database/seeders');
    }

    protected function tearDown(): void
    {
        $this->pdo->rollBack();
    }

    #[Test]
    public function run_cria_o_usuario_admin_com_senha_com_hash(): void
    {
        $executed = $this->runner->run();

        $this->assertContains('0001_create_admin_user', $executed);

        $statement = $this->pdo->prepare('SELECT password, role FROM users WHERE email = ?');
        $statement->execute([self::ADMIN_EMAIL]);
        $row = $statement->fetch();

        $this->assertNotFalse($row);
        $this->assertSame('admin', $row['role']);
        $this->assertTrue(password_verify('password', $row['password']));
    }

    #[Test]
    public function run_e_idempotente_nao_duplica_o_admin(): void
    {
        $this->runner->run();
        $this->runner->run();

        $count = $this->pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
        $count->execute([self::ADMIN_EMAIL]);

        $this->assertSame('1', (string) $count->fetchColumn());
    }
}
