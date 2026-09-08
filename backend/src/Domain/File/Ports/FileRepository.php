<?php

declare(strict_types=1);

namespace App\Domain\File\Ports;

use App\Domain\File\StoredFile;

interface FileRepository
{
    public function findById(string $id): ?StoredFile;

    public function findByPath(string $path): ?StoredFile;

    public function insert(StoredFile $file): void;

    public function delete(string $id): void;

    /**
     * O `path` é o checksum, então entidades com bytes idênticos compartilham a linha -- apagar sem
     * checar quem ainda aponta pra ela quebraria a outra.
     *
     * @return ?string o `path` a remover do storage, ou `null` se alguém ainda referencia o arquivo
     */
    public function deleteIfUnreferenced(string $id): ?string;
}
