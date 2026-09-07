<?php

declare(strict_types=1);

namespace App\Application\Ports;

/** Existe porque o `tmp_name` do PHP some quando a request termina, e o worker precisa achar o arquivo depois. */
interface TempFileStore
{
    public function stage(string $sourcePath, string $key): string;

    public function read(string $path): string;

    /** MIME real, sniffado do conteúdo -- nunca o que o cliente declarou. */
    public function detectMimeType(string $path): string;

    public function discard(string $path): void;
}
