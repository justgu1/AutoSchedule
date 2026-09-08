<?php

declare(strict_types=1);

namespace App\Application\Dealership;

use App\Application\Ports\JobProgress;
use App\Application\Ports\Queue;
use App\Application\Ports\TempFileStore;
use App\Application\Shared\ActorContext;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Domain\Shared\Uuid;

/**
 * Só uma foto por concessionária -- um upload novo substitui a anterior.
 * Otimização (WebP) e gravação rodam fora do request, no worker
 * (`ProcessDealershipPhotoJob`) -- aqui só valida o essencial e copia pro
 * armazenamento temporário compartilhado, porque o `tmp_name` do PHP some
 * assim que a request termina. Quem chamou acompanha o progresso via `job_id`.
 */
final readonly class EnqueueDealershipPhoto
{
    /** Recusa antes de sequer copiar o arquivo pra fila -- algo que vai ser rejeitado de qualquer jeito. */
    private const int MAX_PHOTO_BYTES = 20 * 1024 * 1024;

    public function __construct(
        private DealershipFinder $finder,
        private TempFileStore $tempFiles,
        private JobProgress $jobProgress,
        private Queue $queue,
    ) {
    }

    /** @return string o `job_id` que acompanha o processamento */
    public function __invoke(?string $identifier, string $uploadedTmpPath, string $originalName, int $sizeBytes, ActorContext $context): string
    {
        $dealership = $this->finder->findOrFail($identifier);

        if ($sizeBytes > self::MAX_PHOTO_BYTES) {
            throw new DomainException('Invalid data.', DomainErrorType::Validation, ['image' => 'The image must be at most 20MB.']);
        }

        $jobId = Uuid::v7();
        $sourcePath = $this->tempFiles->stage($uploadedTmpPath, $jobId);

        $this->jobProgress->create($jobId);
        $this->queue->push(ProcessDealershipPhotoJob::class, [
            'job_id' => $jobId,
            'dealership_id' => $dealership->id,
            'source_path' => $sourcePath,
            'original_name' => $originalName,
            'uploaded_by' => $context->actorId,
        ]);

        return $jobId;
    }
}
