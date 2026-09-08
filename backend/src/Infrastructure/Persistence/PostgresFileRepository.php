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
        return $this->hydrateOne(
            $this->connection->execute('SELECT ' . self::COLUMNS . ' FROM files WHERE id = :id', ['id' => $id]),
        );
    }

    public function findByPath(string $path): ?StoredFile
    {
        return $this->hydrateOne(
            $this->connection->execute('SELECT ' . self::COLUMNS . ' FROM files WHERE path = :path', ['path' => $path]),
        );
    }

    public function insert(StoredFile $file): void
    {
        $this->connection->execute(<<<'SQL'
            INSERT INTO files (id, path, original_name, mime_type, size_bytes, checksum, uploaded_by, created_at)
            VALUES (:id, :path, :original_name, :mime_type, :size_bytes, :checksum, :uploaded_by, :created_at)
            SQL, [
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
        $this->connection->execute('DELETE FROM files WHERE id = :id', ['id' => $id]);
    }

    /** Uma sentença só: checar antes e apagar depois abriria janela pra outra referência nascer no meio. */
    public function deleteIfUnreferenced(string $id): ?string
    {
        $statement = $this->connection->execute(<<<'SQL'
            DELETE FROM files f
            WHERE f.id = :id
              AND NOT EXISTS (SELECT 1 FROM vehicle_images vi WHERE vi.file_id = f.id)
              AND NOT EXISTS (SELECT 1 FROM dealerships d WHERE d.photo_file_id = f.id)
            RETURNING path
            SQL, ['id' => $id]);

        $path = $statement->fetchColumn();

        return is_string($path) ? $path : null;
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
