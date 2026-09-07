<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\File\Ports\FileRepository;
use App\Domain\File\StoredFile;

final readonly class PostgresFileRepository implements FileRepository
{
    private const string COLUMNS = 'id, path, original_name, mime_type, size_bytes, checksum, uploaded_by, created_at';

    public function __construct(private DatabaseConnection $connection)
    {
    }

    public function findById(string $id): ?StoredFile
    {
        $statement = $this->connection->pdo()->prepare('SELECT ' . self::COLUMNS . ' FROM files WHERE id = :id');
        $statement->execute(['id' => $id]);

        return $this->hydrateOne($statement);
    }

    public function findByPath(string $path): ?StoredFile
    {
        $statement = $this->connection->pdo()->prepare('SELECT ' . self::COLUMNS . ' FROM files WHERE path = :path');
        $statement->execute(['path' => $path]);

        return $this->hydrateOne($statement);
    }

    public function insert(StoredFile $file): void
    {
        $statement = $this->connection->pdo()->prepare(<<<'SQL'
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
        $statement = $this->connection->pdo()->prepare('DELETE FROM files WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    private function hydrateOne(\PDOStatement $statement): ?StoredFile
    {
        $row = $statement->fetch();

        return $row === false ? null : $this->fromRow(Row::from($row));
    }

    private function fromRow(Row $row): StoredFile
    {
        return new StoredFile(
            id: $row->string('id'),
            path: $row->string('path'),
            originalName: $row->string('original_name'),
            mimeType: $row->string('mime_type'),
            sizeBytes: $row->int('size_bytes'),
            checksum: $row->string('checksum'),
            uploadedBy: $row->nullableString('uploaded_by'),
            createdAt: $row->dateTime('created_at'),
        );
    }
}
