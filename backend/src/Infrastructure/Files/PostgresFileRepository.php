<?php

declare(strict_types=1);

namespace App\Infrastructure\Files;

use App\Domain\Files\Ports\FileRepository;
use App\Domain\Files\StoredFile;

final readonly class PostgresFileRepository implements FileRepository
{
    public function __construct(private \PDO $pdo)
    {
    }

    public function findById(string $id): ?StoredFile
    {
        $statement = $this->pdo->prepare('SELECT * FROM files WHERE id = :id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row === false ? null : $this->fromRow($row);
    }

    public function findByPath(string $path): ?StoredFile
    {
        $statement = $this->pdo->prepare('SELECT * FROM files WHERE path = :path');
        $statement->execute(['path' => $path]);
        $row = $statement->fetch();

        return $row === false ? null : $this->fromRow($row);
    }

    public function insert(StoredFile $file): void
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            INSERT INTO files (id, path, original_name, mime_type, size_bytes, checksum, uploaded_by, created_at)
            VALUES (:id, :path, :original_name, :mime_type, :size_bytes, :checksum, :uploaded_by, :created_at)
            SQL);

        $statement->execute([
            'id' => $file->id,
            'path' => $file->path,
            'original_name' => $file->originalName,
            'mime_type' => $file->mimeType,
            'size_bytes' => $file->sizeBytes,
            'checksum' => $file->checksum,
            'uploaded_by' => $file->uploadedBy,
            'created_at' => $file->createdAt->format(DATE_ATOM),
        ]);
    }

    public function delete(string $id): void
    {
        $statement = $this->pdo->prepare('DELETE FROM files WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    /** @param array<string, mixed> $row */
    private function fromRow(array $row): StoredFile
    {
        return new StoredFile(
            id: $row['id'],
            path: $row['path'],
            originalName: $row['original_name'],
            mimeType: $row['mime_type'],
            sizeBytes: (int) $row['size_bytes'],
            checksum: $row['checksum'],
            uploadedBy: $row['uploaded_by'],
            createdAt: new \DateTimeImmutable($row['created_at']),
        );
    }
}
