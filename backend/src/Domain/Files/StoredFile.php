<?php

declare(strict_types=1);

namespace App\Domain\Files;

use App\Domain\Support\Uuid;

/** Metadado de um objeto gravado no storage (MinIO) -- o conteúdo binário em si nunca passa por aqui. */
final readonly class StoredFile
{
    public function __construct(
        public string $id,
        public string $path,
        public string $originalName,
        public string $mimeType,
        public int $sizeBytes,
        public string $checksum,
        public ?string $uploadedBy,
        public \DateTimeImmutable $createdAt,
    ) {
    }

    public static function register(
        string $path,
        string $originalName,
        string $mimeType,
        int $sizeBytes,
        string $checksum,
        ?string $uploadedBy,
    ): self {
        return new self(
            id: Uuid::v7(),
            path: $path,
            originalName: $originalName,
            mimeType: $mimeType,
            sizeBytes: $sizeBytes,
            checksum: $checksum,
            uploadedBy: $uploadedBy,
            createdAt: new \DateTimeImmutable(),
        );
    }
}
