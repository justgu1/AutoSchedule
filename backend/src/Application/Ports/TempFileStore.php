<?php

declare(strict_types=1);

namespace App\Application\Ports;

/**
 * Área de rascunho compartilhada entre o request e o worker: o `tmp_name` do
 * PHP some quando a request termina, então o arquivo precisa ser copiado pra
 * um caminho que o worker ainda enxergue.
 */
interface TempFileStore
{
    /** Copia `$sourcePath` pra área temporária sob `$key` e devolve o caminho gravado. */
    public function stage(string $sourcePath, string $key): string;

    public function read(string $path): string;

    /** MIME real, sniffado do conteúdo -- nunca o que o cliente declarou. */
    public function detectMimeType(string $path): string;

    /** Remove sem reclamar se o arquivo já não existe. */
    public function discard(string $path): void;
}
