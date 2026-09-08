<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\File\Ports\FileRepository;
use App\Domain\File\StoredFile;

final class InMemoryFileRepository implements FileRepository
{
    /** @var array<string, StoredFile> */
    private array $files = [];

    /**
     * Quem referencia o arquivo vive em outra tabela, então o dublê recebe essa contagem de fora
     * em vez de fingir conhecer galeria e concessionária.
     *
     * @var array<string, int>
     */
    private array $references = [];

    public function findById(string $id): ?StoredFile
    {
        return $this->files[$id] ?? null;
    }

    public function findByPath(string $path): ?StoredFile
    {
        foreach ($this->files as $file) {
            if ($file->path === $path) {
                return $file;
            }
        }

        return null;
    }

    public function insert(StoredFile $file): void
    {
        $this->files[$file->id] = $file;
    }

    public function delete(string $id): void
    {
        unset($this->files[$id]);
    }

    public function deleteIfUnreferenced(string $id): ?string
    {
        $file = $this->findById($id);

        if (!$file instanceof StoredFile || ($this->references[$id] ?? 0) > 0) {
            return null;
        }

        unset($this->files[$id]);

        return $file->path;
    }

    public function referenceCountIs(string $fileId, int $count): void
    {
        $this->references[$fileId] = $count;
    }

    /** @return list<StoredFile> */
    public function all(): array
    {
        return array_values($this->files);
    }
}
