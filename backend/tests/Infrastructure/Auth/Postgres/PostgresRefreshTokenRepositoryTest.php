<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Auth\Postgres;

use App\Domain\Auth\RefreshToken;
use App\Domain\Exceptions\DomainException;
use App\Infrastructure\Auth\Postgres\PostgresRefreshTokenRepository;
use App\Infrastructure\Database\PostgresConnection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Teste de integração: conecta no Postgres real do docker-compose. Isolado
 * por transação (rollback no tearDown), igual o PostgresUserRepositoryTest.
 */
final class PostgresRefreshTokenRepositoryTest extends TestCase
{
    private \PDO $pdo;
    private PostgresRefreshTokenRepository $repository;
    private string $clientId;
    private string $userId;

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
        $this->repository = new PostgresRefreshTokenRepository($this->pdo);
        $this->clientId = $this->insertClient();
        $this->userId = $this->insertUser();
    }

    protected function tearDown(): void
    {
        $this->pdo->rollBack();
    }

    #[Test]
    public function insere_e_encontra_por_token_em_texto_puro(): void
    {
        [$rawToken, $token] = RefreshToken::issue($this->clientId, $this->userId, ['profile:read'], 1_209_600);
        $this->repository->insert($token);

        $found = $this->repository->findByRawToken($rawToken);

        $this->assertNotNull($found);
        $this->assertSame($this->clientId, $found->oauthClientId);
        $this->assertSame(['profile:read'], $found->scopes);
        $this->assertFalse($found->isRevoked());
    }

    #[Test]
    public function rotate_marca_o_token_anterior_como_revogado_e_substituido(): void
    {
        [$rawToken, $token] = RefreshToken::issue($this->clientId, $this->userId, [], 1_209_600);
        $this->repository->insert($token);

        [, $next] = $token->rotate(1_209_600);
        $this->repository->rotate($token, $next);

        $previous = $this->repository->findByRawToken($rawToken);

        $this->assertTrue($previous->isRevoked());
        $this->assertSame($next->id, $previous->replacedById);
    }

    #[Test]
    public function rotate_de_um_token_ja_revogado_falha(): void
    {
        [, $token] = RefreshToken::issue($this->clientId, $this->userId, [], 1_209_600);
        $this->repository->insert($token);

        [, $next] = $token->rotate(1_209_600);
        $this->repository->rotate($token, $next);

        [, $anotherNext] = $token->rotate(1_209_600);

        $this->expectException(DomainException::class);
        $this->repository->rotate($token, $anotherNext);
    }

    #[Test]
    public function revoke_family_revoga_o_token_ativo_restante_da_familia(): void
    {
        [, $first] = RefreshToken::issue($this->clientId, $this->userId, [], 1_209_600);
        $this->repository->insert($first);
        [, $second] = $first->rotate(1_209_600);
        $this->repository->rotate($first, $second); // rotate() já revoga $first; $second continua ativo

        $this->repository->revokeFamily($first->familyId);

        $this->assertTrue($this->isRevoked($second->id));
    }

    private function isRevoked(string $refreshTokenId): bool
    {
        $statement = $this->pdo->prepare('SELECT revoked_at FROM oauth_refresh_tokens WHERE id = ?');
        $statement->execute([$refreshTokenId]);

        return $statement->fetchColumn() !== null;
    }

    private function insertClient(): string
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            INSERT INTO oauth_clients (client_id, name, type, allowed_grant_types, allowed_scopes)
            VALUES ('test-refresh-token-client', 'Test Client', 'public', '{password,refresh_token}', '{}')
            RETURNING id
            SQL);
        $statement->execute();

        return $statement->fetchColumn();
    }

    private function insertUser(): string
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            INSERT INTO users (name, email, password, role)
            VALUES ('Test User', 'test-refresh-token@example.com', 'hash', 'customer')
            RETURNING id
            SQL);
        $statement->execute();

        return $statement->fetchColumn();
    }
}
