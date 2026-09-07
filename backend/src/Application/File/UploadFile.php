<?php

declare(strict_types=1);

namespace App\Application\File;

use App\Application\Ports\TempFileStore;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Domain\File\Ports\FileRepository;
use App\Domain\File\Ports\ImageOptimizer;
use App\Domain\File\Ports\StorageProvider;
use App\Domain\File\StoredFile;
use App\Domain\Shared\Uuid;

/**
 * A ordem é a garantia: o backup local só é descartado depois que storage e metadado confirmam.
 * Qualquer falha no meio deixa o arquivo local intacto pra retry.
 */
final readonly class UploadFile
{
    public function __construct(
        private StorageProvider $storage,
        private FileRepository $files,
        private ImageOptimizer $imageOptimizer,
        private TempFileStore $tempFiles,
    ) {
    }

    /** O whitelist é só `image/webp` porque o otimizador é a autoridade sobre o formato final. */
    public function uploadImage(string $uploadedTmpPath, string $originalName, ?string $uploadedBy): StoredFile
    {
        $optimized = $this->imageOptimizer->optimizeToWebp($uploadedTmpPath);

        try {
            return $this->upload($optimized->path, $originalName, ['image/webp'], $uploadedBy);
        } finally {
            $this->tempFiles->discard($optimized->path);
        }
    }

    /**
     * @param string $uploadedTmpPath caminho local de um upload já recebido pelo PHP (ex: `$_FILES[...]['tmp_name']`)
     * @param list<string> $allowedMimeTypes MIME types aceitos pra este upload -- quem chama decide (foto de concessionária != PDF, por exemplo)
     */
    public function upload(string $uploadedTmpPath, string $originalName, array $allowedMimeTypes, ?string $uploadedBy): StoredFile
    {
        $localPath = $this->tempFiles->stage($uploadedTmpPath, Uuid::v7());
        $mimeType = $this->tempFiles->detectMimeType($localPath);

        if (!in_array($mimeType, $allowedMimeTypes, true)) {
            // Conteúdo rejeitado não passa num retry, então não vale guardar backup.
            $this->tempFiles->discard($localPath);

            throw new DomainException(sprintf('File type "%s" is not allowed.', $mimeType), DomainErrorType::Validation);
        }

        $contents = $this->tempFiles->read($localPath);
        $checksum = hash('sha256', $contents);

        // Path é o checksum, então subir o mesmo conteúdo duas vezes é idempotente.
        $existing = $this->files->findByPath($checksum);

        if ($existing instanceof StoredFile) {
            $this->tempFiles->discard($localPath);

            return $existing;
        }

        $this->storage->put($checksum, $contents, $mimeType);

        $file = StoredFile::register(
            path: $checksum,
            originalName: $originalName,
            mimeType: $mimeType,
            sizeBytes: strlen($contents),
            checksum: $checksum,
            uploadedBy: $uploadedBy,
        );
        $this->files->insert($file);

        $this->tempFiles->discard($localPath);

        return $file;
    }
}
