<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Persistence;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;

/**
 * Conecta como autoschedule_app porque a role admin é superuser e ignora RLS.
 * A fixture é commitada pela admin, então a limpeza é DELETE e não rollback.
 */
#[Group('integration')]
final class OAuthClientRlsPolicyTest extends TestCase
{
    private \PDO $admin;
    private \PDO $rls;
    private string $ownerId;
    private string $otherOwnerId;
    private string $clientId;
    private string $otherClientId;
    private string $systemClientId;

    protected function setUp(): void
    {
        $this->admin = TestDatabase::connect()->pdo();

        $this->ownerId = $this->insertUser('rls-oauth-owner@example.com');
        $this->otherOwnerId = $this->insertUser('rls-oauth-other@example.com');
        $this->clientId = $this->insertClient($this->ownerId);
        $this->otherClientId = $this->insertClient($this->otherOwnerId);
        $this->systemClientId = $this->insertClient(null);

        $this->rls = TestDatabase::connectAsApp()->pdo();
    }

    protected function tearDown(): void
    {
        $statement = $this->admin->prepare('DELETE FROM oauth_clients WHERE id IN (?, ?, ?)');
        $statement->execute([$this->clientId, $this->otherClientId, $this->systemClientId]);
        $statement = $this->admin->prepare('DELETE FROM users WHERE id IN (?, ?)');
        $statement->execute([$this->ownerId, $this->otherOwnerId]);
    }

    #[Test]
    public function dono_so_enxerga_o_proprio_client(): void
    {
        $this->rls->beginTransaction();
        $this->setContext($this->ownerId, 'seller');
        $ids = $this->queryClientIds();
        $this->rls->rollBack();

        $this->assertContains($this->clientId, $ids);
        $this->assertNotContains($this->otherClientId, $ids);
    }

    #[Test]
    public function admin_enxerga_qualquer_client(): void
    {
        $this->rls->beginTransaction();
        $this->setContext($this->ownerId, 'admin');
        $ids = $this->queryClientIds();
        $this->rls->rollBack();

        $this->assertContains($this->clientId, $ids);
        $this->assertContains($this->otherClientId, $ids);
    }

    #[Test]
    public function dono_nao_consegue_revogar_client_de_outro_dono(): void
    {
        $this->rls->beginTransaction();
        $this->setContext($this->ownerId, 'seller');
        $statement = $this->rls->prepare('UPDATE oauth_clients SET revoked_at = now() WHERE id = ?');
        $statement->execute([$this->otherClientId]);
        $affected = $statement->rowCount();
        $this->rls->rollBack();

        $this->assertSame(0, $affected);
    }

    /** `POST /api/oauth/token` roda em contexto de serviço, antes de saber quem é o usuário. */
    #[Test]
    public function contexto_de_servico_enxerga_qualquer_client_pra_autenticar(): void
    {
        $this->rls->beginTransaction();
        $this->rls->exec("SET LOCAL app.is_service_context = 'true'");
        $ids = $this->queryClientIds();
        $this->rls->rollBack();

        $this->assertContains($this->clientId, $ids);
        $this->assertContains($this->otherClientId, $ids);
    }

    /** Sem dono, o client não tem RLS de fato -- só os de usuário dependem de contexto pra aparecer. */
    #[Test]
    public function sem_contexto_setado_so_o_client_sem_dono_e_retornado(): void
    {
        $this->rls->beginTransaction();
        $ids = $this->queryClientIds();
        $this->rls->rollBack();

        $this->assertSame([$this->systemClientId], $ids);
    }

    /** Regressão: cookie velho autentica como outro usuário sem marcar `is_service_context` -- login de novo ainda precisa achar o client. */
    #[Test]
    public function client_sem_dono_e_sempre_visivel_mesmo_autenticado_como_outro_usuario_sem_contexto_de_servico(): void
    {
        $this->rls->beginTransaction();
        $this->setContext($this->ownerId, 'customer');
        $ids = $this->queryClientIds();
        $this->rls->rollBack();

        $this->assertContains($this->systemClientId, $ids);
    }

    private function setContext(string $userId, string $role): void
    {
        $this->rls->exec('SET LOCAL app.current_user_id = ' . $this->rls->quote($userId));
        $this->rls->exec('SET LOCAL app.current_user_role = ' . $this->rls->quote($role));
    }

    /** @return list<string> */
    private function queryClientIds(): array
    {
        $ids = "'{$this->clientId}', '{$this->otherClientId}', '{$this->systemClientId}'";
        $statement = $this->rls->query("SELECT id FROM oauth_clients WHERE id IN ({$ids})");
        $ids = [];

        foreach ($statement === false ? [] : $statement->fetchAll() as $row) {
            \assert(is_array($row) && is_string($row['id']));
            $ids[] = $row['id'];
        }

        return $ids;
    }

    private function insertUser(string $email): string
    {
        $statement = $this->admin->prepare(<<<'SQL'
            INSERT INTO users (name, email, password, role)
            VALUES ('Owner Test', :email, 'hash', 'seller')
            RETURNING id
            SQL);
        $statement->execute(['email' => $email]);

        return (string) $statement->fetchColumn();
    }

    private function insertClient(?string $ownerUserId): string
    {
        $statement = $this->admin->prepare(<<<'SQL'
            INSERT INTO oauth_clients (client_id, name, type, secret_hash, allowed_grant_types, allowed_scopes, owner_user_id)
            VALUES (:client_id, 'RLS Test Client', 'confidential', 'hash', '{client_credentials}', '{}', :owner_user_id)
            RETURNING id
            SQL);
        $statement->execute(['client_id' => 'usr_' . bin2hex(random_bytes(8)), 'owner_user_id' => $ownerUserId]);

        return (string) $statement->fetchColumn();
    }
}
