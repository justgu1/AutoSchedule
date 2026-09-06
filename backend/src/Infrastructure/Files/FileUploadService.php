<?php

declare(strict_types=1);

namespace App\Infrastructure\Files;

use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Domain\Files\Ports\FileRepository;
use App\Domain\Files\StoredFile;
use App\Domain\Ports\StorageProvider;
use App\Domain\Support\Uuid;

/**
 * Orquestra um upload de ponta a ponta: backup local -> validação de
 * conteúdo real -> envio pro storage -> metadado só é gravado depois do
 * storage confirmar sucesso.
 *
 * O backup local (`$tempPath`) é feito ANTES do envio pro storage e só é
 * apagado DEPOIS do `put()` e do `files->insert()` terem sucesso -- se
 * qualquer um dos dois lançar, o arquivo local continua lá pra retry, nada
 * se perde (mesma exigência já documentada no comentário de `backend_tmp`
 * em docker-compose.yaml).
 */
final readonly class FileUploadService
{
    public function __construct(
        private StorageProvider $storage,
        private FileRepository $files,
        private string $tempPath,
    ) {
    }

    /**
     * @param string $uploadedTmpPath caminho local de um upload já recebido pelo PHP (ex: `$_FILES[...]['tmp_name']`)
     * @param list<string> $allowedMimeTypes MIME types aceitos pra este upload -- quem chama decide (foto de concessionária != PDF, por exemplo)
     */
    public function upload(string $uploadedTmpPath, string $originalName, array $allowedMimeTypes, ?string $uploadedBy): StoredFile
    {
        $localPath = sprintf('%s/%s', rtrim($this->tempPath, '/'), Uuid::v7());

        if (!copy($uploadedTmpPath, $localPath)) {
            throw new \RuntimeException('Could not back up the uploaded file locally before sending it to storage.');
        }

        $mimeType = $this->detectRealMimeType($localPath);

        if (!in_array($mimeType, $allowedMimeTypes, true)) {
            // Conteúdo rejeitado nunca vai passar num retry -- sem motivo pra guardar backup.
            @unlink($localPath);

            throw new DomainException(sprintf('File type "%s" is not allowed.', $mimeType), DomainErrorType::Validation);
        }

        $contents = file_get_contents($localPath);
        $checksum = hash('sha256', $contents);

        // Path content-addressed (checksum) -- upload do mesmo conteúdo duas
        // vezes (ex: retry após falha parcial, ou a mesma foto reaproveitada
        // noutra concessionária) é idempotente: mesma key no storage, mesma
        // linha em `files`.
        $existing = $this->files->findByPath($checksum);

        if ($existing instanceof StoredFile) {
            @unlink($localPath);

            return $existing;
        }

        // Ponto crítico: só chega no unlink() de baixo se put() E insert()
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

        unlink($localPath);

        return $file;
    }

    private function detectRealMimeType(string $path): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $path);
        finfo_close($finfo);

        return $mimeType !== false ? $mimeType : 'application/octet-stream';
    }
}
