<?php

declare(strict_types=1);

namespace App\Domain\Files\Ports;

use App\Domain\Files\StoredFile;

interface FileRepository
{
    public function findById(string $id): ?StoredFile;

    public function findByPath(string $path): ?StoredFile;

    public function insert(StoredFile $file): void;

    public function delete(string $id): void;
}
