<?php

declare(strict_types=1);

namespace App\Application\Dealership;

use App\Domain\Dealership\Dealership;
use App\Domain\File\Ports\FileRepository;
use App\Domain\File\Ports\StorageProvider;
use App\Domain\File\StoredFile;

final readonly class DealershipPhotos
{
    public function __construct(
        private FileRepository $files,
        private StorageProvider $storage,
    ) {
    }

    public function urlFor(Dealership $dealership): ?string
    {
        if ($dealership->photoFileId === null) {
            return null;
        }

        $file = $this->files->findById($dealership->photoFileId);

        return $file instanceof StoredFile ? $this->storage->url($file->path) : null;
    }

    public function delete(?string $fileId): void
    {
        if ($fileId === null) {
            return;
        }

        $path = $this->files->deleteIfUnreferenced($fileId);

        if ($path !== null) {
            $this->storage->delete($path);
        }
    }
}
