<?php

declare(strict_types=1);

namespace App\Infrastructure\File;

use App\Application\Ports\TempFileStore;

/**
 * Diretório local compartilhado entre PHP-FPM e o worker (volume `backend_tmp`
 * no compose, `emptyDir` no k8s) -- é o que faz um arquivo recebido numa
 * request continuar existindo quando o job roda noutro processo.
 */
final readonly class LocalTempFileStore implements TempFileStore
{
    public function __construct(private string $tempPath)
    {
    }

    public function stage(string $sourcePath, string $key): string
    {
        $target = sprintf('%s/%s', rtrim($this->tempPath, '/'), $key);

        if (!copy($sourcePath, $target)) {
            throw new \RuntimeException('Could not back up the uploaded file locally before sending it to storage.');
        }

        return $target;
    }

    public function read(string $path): string
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new \RuntimeException(sprintf('Could not read the staged file at "%s".', $path));
        }

        return $contents;
    }

    public function detectMimeType(string $path): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        if ($finfo === false) {
            return 'application/octet-stream';
        }

        $mimeType = finfo_file($finfo, $path);

        return $mimeType !== false ? $mimeType : 'application/octet-stream';
    }

    public function discard(string $path): void
    {
        @unlink($path);
    }
}
