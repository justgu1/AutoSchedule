<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

final readonly class UploadedFile
{
    public function __construct(
        public string $tmpName,
        public string $originalName,
        public int $size,
        public int $error,
    ) {
    }

    public function isValid(): bool
    {
        return $this->error === UPLOAD_ERR_OK;
    }
}
