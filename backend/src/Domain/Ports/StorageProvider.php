<?php

declare(strict_types=1);

namespace App\Domain\Ports;

/**
 * Armazenamento de objetos (imagens de concessionária/veículo). Quem chama
 * já validou conteúdo real + MIME type antes -- esta porta só grava/lê/apaga.
 */
interface StorageProvider
{
    public function put(string $path, string $contents, string $mimeType): void;

    public function url(string $path): string;

    public function delete(string $path): void;
}
