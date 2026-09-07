<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Persistence;

use App\Infrastructure\Persistence\MigrationRunner;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;

/** Postgres tem DDL transacional, então o rollback do tearDown desfaz até a tabela criada. */
#[Group('integration')]
final class MigrationRunnerTest extends TestCase
{
    private \PDO $pdo;
    private MigrationRunner $runner;

    protected function setUp(): void
    {
        $connection = TestDatabase::connect();
        $this->pdo = $connection->pdo();

        $this->pdo->beginTransaction();

        // Ordem importa: CASCADE derruba a constraint de FK, não a tabela dependente, que precisa do próprio DROP.
        // Estender a lista quando uma migration nova criar tabela.
        $this->pdo->exec('DROP TABLE IF EXISTS dealership_images CASCADE');
        $this->pdo->exec('DROP TABLE IF EXISTS dealerships CASCADE');
        $this->pdo->exec('DROP TABLE IF EXISTS zip_code_cache CASCADE');
        $this->pdo->exec('DROP TYPE IF EXISTS dealership_status');
        $this->pdo->exec('DROP TABLE IF EXISTS files CASCADE');
        $this->pdo->exec('DROP TABLE IF EXISTS audit_logs CASCADE');
        $this->pdo->exec('DROP TABLE IF EXISTS user_identities CASCADE');
        $this->pdo->exec('DROP TABLE IF EXISTS password_reset_tokens CASCADE');
        $this->pdo->exec('DROP TABLE IF EXISTS oauth_refresh_tokens CASCADE');
        $this->pdo->exec('DROP TABLE IF EXISTS oauth_clients CASCADE');
        $this->pdo->exec('DROP TABLE IF EXISTS users CASCADE');
        $this->pdo->exec('DROP TYPE IF EXISTS user_status');

        if ($this->pdo->query("SELECT to_regclass('public.migrations')")->fetchColumn() !== null) {
            $this->pdo->exec('DELETE FROM migrations');
        }

        $this->runner = new MigrationRunner($this->pdo, dirname(__DIR__, 3) . '/database/migrations');
    }

    protected function tearDown(): void
    {
        $this->pdo->rollBack();
    }

    #[Test]
    public function run_aplica_a_migration_de_users_e_cria_a_tabela(): void
    {
        $applied = $this->runner->run();

        $this->assertContains('2026_09_03_000001_create_users_table', $applied);

        $columns = $this->pdo
            ->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'users'")
            ->fetchAll(\PDO::FETCH_COLUMN);

        $this->assertContains('email', $columns);
        $this->assertContains('role', $columns);
    }

    #[Test]
    public function run_e_idempotente(): void
    {
        $this->runner->run();

        $this->assertSame([], $this->runner->run());
    }

    #[Test]
    public function rollback_desfaz_o_batch_mais_recente(): void
    {
        $applied = $this->runner->run();

        $rolledBack = $this->runner->rollback();

        $this->assertSame(array_reverse($applied), $rolledBack);

        $tableExists = $this->pdo->query("SELECT to_regclass('public.users')")->fetchColumn();
        $this->assertNull($tableExists);

        $remaining = $this->pdo->query('SELECT COUNT(*) FROM migrations')->fetchColumn();
        $this->assertSame('0', (string) $remaining);
    }

    #[Test]
    public function rollback_sem_nada_aplicado_devolve_lista_vazia(): void
    {
        $this->assertSame([], $this->runner->rollback());
    }
}
