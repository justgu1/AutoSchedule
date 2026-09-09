<?php

declare(strict_types=1);

namespace App\Infrastructure\File;

use App\Application\Ports\TempFileStore;
use App\Domain\File\Ports\StorageProvider;

/**
 * Backend e worker são processos (pods) diferentes, sem disco em comum -- `stage()` grava no
 * mesmo bucket S3 que já é compartilhado pelos dois, sob um prefixo `tmp/` descartado depois.
 */
final readonly class S3TempFileStore implements TempFileStore
{
    private const string PREFIX = 'tmp/';

    public function __construct(private StorageProvider $storage)
    {
    }

    public function stage(string $sourcePath, string $key): string
    {
        $contents = file_get_contents($sourcePath);

        if ($contents === false) {
            throw new \RuntimeException(sprintf('Could not read the uploaded file at "%s".', $sourcePath));
        }

        $path = self::PREFIX . $key;
        $this->storage->put($path, $contents, 'application/octet-stream');

        return $path;
    }

    public function read(string $path): string
    {
        return $this->storage->get($path);
    }

    public function detectMimeType(string $path): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        if ($finfo === false) {
            return 'application/octet-stream';
        }

        $mimeType = finfo_buffer($finfo, $this->read($path));

        return $mimeType !== false ? $mimeType : 'application/octet-stream';
    }

    public function discard(string $path): void
    {
        $this->storage->delete($path);
    }
}
