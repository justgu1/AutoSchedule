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
        $this->connection = new PostgresConnection(
            driver: getenv('DB_DRIVER') ?: 'pgsql',
            host: getenv('DB_HOST') ?: '127.0.0.1',
            port: (int) (getenv('DB_PORT') ?: 5432),
            database: getenv('DB_DATABASE') ?: 'autoschedule',
            username: getenv('DB_APP_USERNAME') ?: 'autoschedule_app',
            password: getenv('DB_APP_PASSWORD') ?: 'changeme',
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

        $logger->record(AuditEvent::LoginFailed, null, ['email' => 'ada@example.com'], '203.0.113.9', 'phpunit-agent');

        $row = $this->connection->pdo()
            ->query("SELECT event, auditable_type, ip_address, user_agent, new_values FROM audit_logs ORDER BY created_at DESC LIMIT 1")
            ->fetch();

        $this->assertSame(AuditEvent::LoginFailed->value, $row['event']);
        $this->assertSame('User', $row['auditable_type']);
        $this->assertSame('203.0.113.9', $row['ip_address']);
        $this->assertSame('phpunit-agent', $row['user_agent']);
        $this->assertSame(['email' => 'ada@example.com'], json_decode($row['new_values'], true));
    }

    #[Test]
    public function falha_ao_gravar_nao_propaga_excecao(): void
    {
        // PDO quebrado (host inexistente) força o catch a acionar -- é o
        // ponto que importa: auditoria nunca pode derrubar a resposta principal.
        $brokenPdo = new \PDO('sqlite::memory:');
        $logger = new PostgresAuditLogger($brokenPdo, new NullLogger());

        $logger->record(AuditEvent::LoginSucceeded, null, [], '127.0.0.1', null);

        $this->addToAssertionCount(1);
    }
}

final class NullLogger extends AbstractLogger
{
    public function log($level, \Stringable|string $message, array $context = []): void
    {
    }
}
