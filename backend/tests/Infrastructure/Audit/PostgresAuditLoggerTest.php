<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Audit;

use App\Domain\Audit\AuditEvent;
use App\Infrastructure\Audit\PostgresAuditLogger;
use App\Infrastructure\Database\PostgresConnection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

/**
 * Teste de integração: grava no Postgres real -- precisa rodar dentro do
 * compose (mesmo padrão dos outros testes de Postgres).
 */
final class PostgresAuditLoggerTest extends TestCase
{
    private PostgresConnection $connection;

    protected function setUp(): void
    {
        // Role padrão (não autoschedule_app): a fixture de users pro teste de
        // actor/target precisa de INSERT sem passar pelo RLS, que exige
        // app.current_user_role='admin' via SET LOCAL -- PostgresAuditLogger em
        // si não lida com RLS, só grava em audit_logs.
        $this->connection = new PostgresConnection(
            driver: getenv('DB_DRIVER') ?: 'pgsql',
            host: getenv('DB_HOST') ?: '127.0.0.1',
            port: (int) (getenv('DB_PORT') ?: 5432),
            database: getenv('DB_DATABASE') ?: 'autoschedule',
            username: getenv('DB_USERNAME') ?: 'pgsql',
            password: getenv('DB_PASSWORD') ?: 'password',
        );
        $this->connection->pdo()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->connection->pdo()->rollBack();
    }

    #[Test]
    public function grava_o_evento_com_ip_user_agent_e_contexto(): void
    {
        $logger = new PostgresAuditLogger($this->connection->pdo(), new NullLogger());

        $logger->record(AuditEvent::LoginFailed, null, null, ['email' => 'ada@example.com'], '203.0.113.9', 'phpunit-agent');

        $row = $this->connection->pdo()
            ->query('SELECT event, auditable_type, ip_address, user_agent, new_values FROM audit_logs ORDER BY created_at DESC LIMIT 1')
            ->fetch();

        $this->assertSame(AuditEvent::LoginFailed->value, $row['event']);
        $this->assertSame('User', $row['auditable_type']);
        $this->assertSame('203.0.113.9', $row['ip_address']);
        $this->assertSame('phpunit-agent', $row['user_agent']);
        $this->assertSame(['email' => 'ada@example.com'], json_decode($row['new_values'], true));
    }

    #[Test]
    public function grava_actor_e_target_separados_quando_um_admin_age_sobre_outro_usuario(): void
    {
        $admin = $this->insertUser('admin@example.com');
        $target = $this->insertUser('target@example.com');
        $logger = new PostgresAuditLogger($this->connection->pdo(), new NullLogger());

        $logger->record(AuditEvent::UserCreated, $admin, $target, ['role' => 'seller'], '203.0.113.9', 'phpunit-agent');

        $row = $this->connection->pdo()
            ->query('SELECT actor_id, user_id FROM audit_logs ORDER BY created_at DESC LIMIT 1')
            ->fetch();

        $this->assertSame($admin, $row['actor_id']);
        $this->assertSame($target, $row['user_id']);
    }

    #[Test]
    public function falha_ao_gravar_nao_propaga_excecao(): void
    {
        // PDO quebrado (host inexistente) força o catch a acionar -- é o
        // ponto que importa: auditoria nunca pode derrubar a resposta principal.
        $brokenPdo = new \PDO('sqlite::memory:');
        $logger = new PostgresAuditLogger($brokenPdo, new NullLogger());

        $logger->record(AuditEvent::LoginSucceeded, null, null, [], '127.0.0.1', null);

        $this->addToAssertionCount(1);
    }

    private function insertUser(string $email): string
    {
        $statement = $this->connection->pdo()->prepare(<<<'SQL'
            INSERT INTO users (name, email, password, role) VALUES ('Test User', :email, 'hash', 'customer') RETURNING id
            SQL);
        $statement->execute(['email' => $email]);

        return $statement->fetchColumn();
    }
}

final class NullLogger extends AbstractLogger
{
    public function log($level, \Stringable|string $message, array $context = []): void
    {
    }
}
