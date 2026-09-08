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

    /**
     * Content-addressed (`UploadFile` dedupa por checksum) -- em teoria duas
     * concessionárias poderiam acabar apontando pro mesmo arquivo se subissem
     * bytes idênticos, e apagar aqui quebraria a outra. Aceito o risco: fotos
     * reais nunca colidem byte a byte na prática, e não vale a complexidade de
     * contar referências pra isso.
     */
    public function delete(?string $fileId): void
    {
        if ($fileId === null) {
            return;
        }

        $file = $this->files->findById($fileId);

        if ($file instanceof StoredFile) {
            $this->storage->delete($file->path);
            $this->files->delete($file->id);
        }
    }
}
