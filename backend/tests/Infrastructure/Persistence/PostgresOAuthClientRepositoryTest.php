<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Persistence;

use App\Domain\Auth\ClientType;
use App\Domain\Auth\GrantType;
use App\Domain\Auth\OAuthClient;
use App\Infrastructure\Persistence\PostgresOAuthClientRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;

/**
 * Teste de integração: conecta no Postgres real do docker-compose. Isolado
 * por transação (rollback no tearDown), igual o PostgresUserRepositoryTest.
 */
#[Group('integration')]
final class PostgresOAuthClientRepositoryTest extends TestCase
{
    private \PDO $pdo;
    private PostgresOAuthClientRepository $repository;

    protected function setUp(): void
    {
        $connection = TestDatabase::connect();
        $this->pdo = $connection->pdo();

        $this->pdo->beginTransaction();
        $this->repository = new PostgresOAuthClientRepository($connection);
    }

    protected function tearDown(): void
    {
        $this->pdo->rollBack();
    }

    #[Test]
    public function encontra_um_client_publico_e_faz_o_parse_dos_arrays(): void
    {
        $this->insertClient(
            clientId: 'test-public-client',
            type: 'public',
            secretHash: null,
            allowedGrantTypes: '{password,refresh_token}',
            redirectUris: '{urn:test:headless}',
            allowedScopes: '{profile:read,profile:write}',
        );

        $client = $this->repository->findByClientId('test-public-client');

        $this->assertNotNull($client);
        $this->assertSame(ClientType::Public, $client->type);
        $this->assertNull($client->secretHash);
        $this->assertSame([GrantType::Password, GrantType::RefreshToken], $client->allowedGrantTypes);
        $this->assertSame(['urn:test:headless'], $client->redirectUris);
        $this->assertSame(['profile:read', 'profile:write'], $client->allowedScopes);
    }

    #[Test]
    public function encontra_um_client_confidencial_com_secret(): void
    {
        $this->insertClient(
            clientId: 'test-service-client',
            type: 'confidential',
            secretHash: password_hash('some-secret', PASSWORD_ARGON2ID),
            allowedGrantTypes: '{client_credentials}',
            redirectUris: null,
            allowedScopes: '{service:internal}',
        );

        $client = $this->repository->findByClientId('test-service-client');

        $this->assertNotNull($client);
        $this->assertSame(ClientType::Confidential, $client->type);
        $this->assertTrue($client->verifySecret('some-secret'));
        $this->assertSame([], $client->redirectUris);
    }

    #[Test]
    public function find_by_client_id_devolve_null_quando_nao_existe(): void
    {
        $this->assertNull($this->repository->findByClientId('does-not-exist'));
    }

    #[Test]
    public function find_by_client_id_nao_devolve_client_revogado(): void
    {
        [$secret, $client] = OAuthClient::createForOwner($this->insertSellerUser(), 'Minha integração');
        $this->repository->insert($client);
        $this->repository->update($client->revoked());

        $this->assertTrue($client->verifySecret($secret));
        $this->assertNull($this->repository->findByClientId($client->clientId));
    }

    #[Test]
    public function insert_e_find_by_id_for_owner_fazem_a_ida_e_volta(): void
    {
        $ownerId = $this->insertSellerUser();
        [, $client] = OAuthClient::createForOwner($ownerId, 'Minha integração');

        $this->repository->insert($client);
        $found = $this->repository->findByIdForOwner($client->id, $ownerId);

        $this->assertNotNull($found);
        $this->assertSame($client->clientId, $found->clientId);
        $this->assertSame($ownerId, $found->ownerUserId);
        $this->assertSame([], $found->allowedScopes);
    }

    #[Test]
    public function find_by_id_for_owner_devolve_null_pro_dono_errado(): void
    {
        $ownerId = $this->insertSellerUser();
        $otherOwnerId = $this->insertSellerUser();
        [, $client] = OAuthClient::createForOwner($ownerId, 'Minha integração');
        $this->repository->insert($client);

        $this->assertNull($this->repository->findByIdForOwner($client->id, $otherOwnerId));
    }

    #[Test]
    public function find_all_for_owner_traz_so_os_clients_daquele_dono(): void
    {
        $ownerId = $this->insertSellerUser();
        $otherOwnerId = $this->insertSellerUser();
        [, $mine] = OAuthClient::createForOwner($ownerId, 'Minha integração');
        [, $someoneElses] = OAuthClient::createForOwner($otherOwnerId, 'Outra integração');
        $this->repository->insert($mine);
        $this->repository->insert($someoneElses);

        $ids = array_map(static fn (OAuthClient $c): string => $c->id, $this->repository->findAllForOwner($ownerId));

        $this->assertSame([$mine->id], $ids);
    }

    #[Test]
    public function update_persiste_secret_rotacionado_e_revogacao(): void
    {
        $ownerId = $this->insertSellerUser();
        [, $client] = OAuthClient::createForOwner($ownerId, 'Minha integração');
        $this->repository->insert($client);

        [$newSecret, $rotated] = $client->rotateSecret();
        $this->repository->update($rotated);
        $this->repository->update($rotated->revoked());

        $stored = $this->repository->findByIdForOwner($client->id, $ownerId);
        $this->assertNotNull($stored);
        $this->assertTrue($stored->isRevoked());
        $this->assertTrue($stored->verifySecret($newSecret));
    }

    private function insertSellerUser(): string
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            INSERT INTO users (name, email, password, role)
            VALUES ('Seller Test', :email, 'hash', 'seller')
            RETURNING id
            SQL);
        $statement->execute(['email' => 'seller-' . bin2hex(random_bytes(8)) . '@example.com']);

        return (string) $statement->fetchColumn();
    }

    private function insertClient(
        string $clientId,
        string $type,
        ?string $secretHash,
        string $allowedGrantTypes,
        ?string $redirectUris,
        string $allowedScopes,
    ): void {
        $statement = $this->pdo->prepare(<<<'SQL'
            INSERT INTO oauth_clients (client_id, name, type, secret_hash, allowed_grant_types, redirect_uris, allowed_scopes)
            VALUES (:client_id, :name, :type, :secret_hash, :allowed_grant_types, :redirect_uris, :allowed_scopes)
            SQL);

        $statement->execute([
            'client_id' => $clientId,
            'name' => $clientId,
            'type' => $type,
            'secret_hash' => $secretHash,
            'allowed_grant_types' => $allowedGrantTypes,
            'redirect_uris' => $redirectUris,
            'allowed_scopes' => $allowedScopes,
        ]);
    }
}
