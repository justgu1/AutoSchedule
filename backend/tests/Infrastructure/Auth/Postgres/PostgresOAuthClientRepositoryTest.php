<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Auth\Postgres;

use App\Domain\Auth\ClientType;
use App\Domain\Auth\GrantType;
use App\Infrastructure\Auth\Postgres\PostgresOAuthClientRepository;
use App\Infrastructure\Database\PostgresConnection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Teste de integração: conecta no Postgres real do docker-compose. Isolado
 * por transação (rollback no tearDown), igual o PostgresUserRepositoryTest.
 */
final class PostgresOAuthClientRepositoryTest extends TestCase
{
    private \PDO $pdo;
    private PostgresOAuthClientRepository $repository;

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
        $this->repository = new PostgresOAuthClientRepository($this->pdo);
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
            allowedGrantTypes: '{authorization_code,refresh_token}',
            redirectUris: '{urn:test:headless}',
            allowedScopes: '{profile:read,profile:write}',
        );

        $client = $this->repository->findByClientId('test-public-client');

        $this->assertNotNull($client);
        $this->assertSame(ClientType::Public, $client->type);
        $this->assertNull($client->secretHash);
        $this->assertSame([GrantType::AuthorizationCode, GrantType::RefreshToken], $client->allowedGrantTypes);
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
