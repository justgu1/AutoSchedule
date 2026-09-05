<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Database;

use App\Infrastructure\Database\MigrationRunner;
use App\Infrastructure\Database\PostgresConnection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Teste de integração: conecta no Postgres real do docker-compose 
 * e roda as migrations reais de backend/database/migrations/
 * Cada teste roda dentro de uma transação
 * desfeita no tearDown — Postgres suporta DDL transacional, então as
 * tabelas/tipos/registros criados somem sem sujar o banco de dev.
 */
final class MigrationRunnerTest extends TestCase
{
    private \PDO $pdo;
    private MigrationRunner $runner;

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

        // Dropa toda tabela que uma migration real cria, pra run() poder
        // aplicar tudo de novo do zero dentro da transação deste teste.
        // Estender essa lista sempre que uma migration nova criar tabela.
        // Ordem importa aqui: CASCADE numa tabela referenciada só dropa a
        // *constraint* de FK na tabela dependente, não a tabela em si -- por
        // isso toda tabela com FK ainda precisa do próprio DROP explícito.
        $this->pdo->exec('DROP TABLE IF EXISTS audit_logs CASCADE');
        $this->pdo->exec('DROP TABLE IF EXISTS oauth_refresh_tokens CASCADE');
        $this->pdo->exec('DROP TABLE IF EXISTS oauth_clients CASCADE');
        $this->pdo->exec('DROP TABLE IF EXISTS users CASCADE');

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
