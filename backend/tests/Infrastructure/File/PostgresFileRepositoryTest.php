<?php

declare(strict_types=1);

namespace Tests\Infrastructure\File;

use App\Domain\File\StoredFile;
use App\Infrastructure\File\PostgresFileRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;

/**
 * Teste de integração: conecta no Postgres real do docker-compose. Isolado
 * por transação (rollback no tearDown), igual PostgresUserRepositoryTest.
 */
#[Group('integration')]
final class PostgresFileRepositoryTest extends TestCase
{
    private \PDO $pdo;
    private PostgresFileRepository $repository;

    protected function setUp(): void
    {
        $connection = TestDatabase::connect();
        $this->pdo = $connection->pdo();

        $this->pdo->beginTransaction();
        $this->repository = new PostgresFileRepository($connection);
    }

    protected function tearDown(): void
    {
        $this->pdo->rollBack();
    }

    #[Test]
    public function insere_e_encontra_por_id(): void
    {
        $file = StoredFile::register('ab/abcdef', 'foto.jpg', 'image/jpeg', 1234, 'abcdef', uploadedBy: null);
        $this->repository->insert($file);

        $found = $this->repository->findById($file->id);

        $this->assertNotNull($found);
        $this->assertSame($file->path, $found->path);
        $this->assertSame($file->originalName, $found->originalName);
        $this->assertSame($file->mimeType, $found->mimeType);
        $this->assertSame($file->sizeBytes, $found->sizeBytes);
        $this->assertSame($file->checksum, $found->checksum);
    }

    #[Test]
    public function insere_e_encontra_por_path(): void
    {
        $file = StoredFile::register('ab/abcdef', 'foto.jpg', 'image/jpeg', 1234, 'abcdef', uploadedBy: null);
        $this->repository->insert($file);

        $found = $this->repository->findByPath('ab/abcdef');

        $this->assertNotNull($found);
        $this->assertSame($file->id, $found->id);
    }

    #[Test]
    public function find_by_path_devolve_null_quando_nao_existe(): void
    {
        $this->assertNull($this->repository->findByPath('nao/existe'));
    }

    #[Test]
    public function delete_remove_a_linha(): void
    {
        $file = StoredFile::register('ab/abcdef', 'foto.jpg', 'image/jpeg', 1234, 'abcdef', uploadedBy: null);
        $this->repository->insert($file);

        $this->repository->delete($file->id);

        $this->assertNull($this->repository->findById($file->id));
    }
}
