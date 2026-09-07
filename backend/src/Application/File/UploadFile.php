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
 * Orquestra um upload de ponta a ponta: backup local -> validação de
 * conteúdo real -> envio pro storage -> metadado só é gravado depois do
 * storage confirmar sucesso.
 *
 * O backup local é feito ANTES do envio pro storage e só é descartado DEPOIS
 * do `put()` e do `files->insert()` terem sucesso -- se qualquer um dos dois
 * lançar, o arquivo local continua lá pra retry, nada se perde (mesma
 * exigência já documentada no comentário de `backend_tmp` em
 * docker-compose.yaml).
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

    /**
     * Toda foto do site passa por aqui em vez de `upload()` direto -- converte
     * pra WebP e redimensiona pro padrão do site antes de gravar, então o
     * MIME permitido é sempre só `image/webp` (o que sai do otimizador).
     */
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
            // Conteúdo rejeitado nunca vai passar num retry -- sem motivo pra guardar backup.
            $this->tempFiles->discard($localPath);

            throw new DomainException(sprintf('File type "%s" is not allowed.', $mimeType), DomainErrorType::Validation);
        }

        $contents = $this->tempFiles->read($localPath);
        $checksum = hash('sha256', $contents);

        // Path content-addressed (checksum) -- upload do mesmo conteúdo duas
        // vezes (ex: retry após falha parcial, ou a mesma foto reaproveitada
        // noutra concessionária) é idempotente: mesma key no storage, mesma
        // linha em `files`.
        $existing = $this->files->findByPath($checksum);

        if ($existing instanceof StoredFile) {
            $this->tempFiles->discard($localPath);

            return $existing;
        }

        // Ponto crítico: só chega no discard() de baixo se put() E insert()
        // tiverem sucesso. Qualquer exceção de um dos dois propaga e deixa
        // `$localPath` intacto -- é o backup que garante que nada se perde.
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
