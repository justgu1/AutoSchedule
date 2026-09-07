<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Auth\Postgres;

use App\Domain\Auth\PasswordResetToken;
use App\Infrastructure\Auth\Postgres\PostgresPasswordResetTokenRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;

/**
 * Teste de integração: conecta no Postgres real do docker-compose. Isolado
 * por transação (rollback no tearDown), igual o PostgresRefreshTokenRepositoryTest.
 */
#[Group('integration')]
final class PostgresPasswordResetTokenRepositoryTest extends TestCase
{
    private \PDO $pdo;
    private PostgresPasswordResetTokenRepository $repository;
    private string $userId;

    protected function setUp(): void
    {
        $connection = TestDatabase::connect();
        $this->pdo = $connection->pdo();

        $this->pdo->beginTransaction();
        $this->repository = new PostgresPasswordResetTokenRepository($connection);
        $this->userId = $this->insertUser();
    }

    protected function tearDown(): void
    {
        $this->pdo->rollBack();
    }

    #[Test]
    public function insere_e_encontra_por_token_em_texto_puro(): void
    {
        [$rawToken, $token] = PasswordResetToken::issue($this->userId, 3600);
        $this->repository->insert($token);

        $found = $this->repository->findByRawToken($rawToken);

        $this->assertNotNull($found);
        $this->assertSame($this->userId, $found->userId);
        $this->assertFalse($found->isUsed());
    }

    #[Test]
    public function find_by_raw_token_devolve_null_pra_token_inexistente(): void
    {
        $this->assertNull($this->repository->findByRawToken('nao-existe'));
    }

    #[Test]
    public function mark_used_marca_o_token_como_usado(): void
    {
        [$rawToken, $token] = PasswordResetToken::issue($this->userId, 3600);
        $this->repository->insert($token);

        $this->repository->markUsed($token->id);

        $this->assertTrue($this->repository->findByRawToken($rawToken)->isUsed());
    }

    #[Test]
    public function invalidate_all_for_user_marca_todo_token_pendente_do_usuario_como_usado(): void
    {
        [$rawA, $tokenA] = PasswordResetToken::issue($this->userId, 3600);
        [$rawB, $tokenB] = PasswordResetToken::issue($this->userId, 3600);
        $this->repository->insert($tokenA);
        $this->repository->insert($tokenB);

        $this->repository->invalidateAllForUser($this->userId);

        $this->assertTrue($this->repository->findByRawToken($rawA)->isUsed());
        $this->assertTrue($this->repository->findByRawToken($rawB)->isUsed());
    }

    private function insertUser(): string
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            INSERT INTO users (name, email, password, role)
            VALUES ('Test User', 'test-password-reset@example.com', 'hash', 'customer')
            RETURNING id
            SQL);
        $statement->execute();

        return $statement->fetchColumn();
    }
}
