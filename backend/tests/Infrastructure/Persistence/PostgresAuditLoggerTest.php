<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Persistence;

use App\Domain\Audit\AuditEvent;
use App\Infrastructure\Persistence\PostgresAuditLogger;
use App\Infrastructure\Persistence\PostgresConnection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Tests\Support\FixedConnection;
use Tests\Support\TestDatabase;

#[Group('integration')]
final class PostgresAuditLoggerTest extends TestCase
{
    private PostgresConnection $connection;

    protected function setUp(): void
    {
        // Fixture de users precisa furar o RLS; o próprio PostgresAuditLogger não lida com isso.
        $this->connection = TestDatabase::connect();
        $this->connection->pdo()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->connection->pdo()->rollBack();
    }

    #[Test]
    public function grava_o_evento_com_ip_user_agent_e_contexto(): void
    {
        $logger = new PostgresAuditLogger($this->connection, new NullLogger());

        $logger->record(AuditEvent::LoginFailed, null, 'User', null, ['email' => 'ada@example.com'], '203.0.113.9', 'phpunit-agent');

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
        $logger = new PostgresAuditLogger($this->connection, new NullLogger());

        $logger->record(AuditEvent::UserCreated, $admin, 'User', $target, ['role' => 'seller'], '203.0.113.9', 'phpunit-agent');

        $row = $this->connection->pdo()
            ->query('SELECT actor_id, user_id FROM audit_logs ORDER BY created_at DESC LIMIT 1')
            ->fetch();

        $this->assertSame($admin, $row['actor_id']);
        $this->assertSame($target, $row['user_id']);
    }

    #[Test]
    public function auditable_type_diferente_de_user_nao_preenche_user_id(): void
    {
        // user_id tem FK pra users e auditable_id não, daí a assimetria.
        $dealershipId = '00000000-0000-4000-8000-000000000001';
        $logger = new PostgresAuditLogger($this->connection, new NullLogger());

        $logger->record(AuditEvent::DealershipCreated, null, 'Dealership', $dealershipId, [], '203.0.113.9', 'phpunit-agent');

        $row = $this->connection->pdo()
            ->query('SELECT auditable_type, auditable_id, user_id FROM audit_logs ORDER BY created_at DESC LIMIT 1')
            ->fetch();

        $this->assertSame('Dealership', $row['auditable_type']);
        $this->assertSame($dealershipId, $row['auditable_id']);
        $this->assertNull($row['user_id']);
    }

    #[Test]
    public function falha_ao_gravar_nao_propaga_excecao(): void
    {
        // PDO quebrado de propósito: auditoria nunca pode derrubar a resposta principal.
        $logger = new PostgresAuditLogger(new FixedConnection(new \PDO('sqlite::memory:')), new NullLogger());

        $logger->record(AuditEvent::LoginSucceeded, null, 'User', null, [], '127.0.0.1', null);

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
