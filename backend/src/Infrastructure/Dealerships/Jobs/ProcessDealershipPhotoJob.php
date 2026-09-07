<?php

declare(strict_types=1);

namespace App\Infrastructure\Dealerships\Jobs;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\Ports\AuditLogger;
use App\Domain\Dealerships\Dealership;
use App\Domain\Dealerships\Ports\DealershipRepository;
use App\Domain\Files\Ports\FileRepository;
use App\Domain\Files\StoredFile;
use App\Domain\Ports\Job;
use App\Domain\Ports\StorageProvider;
use App\Infrastructure\Files\FileUploadService;
use App\Infrastructure\Jobs\JobStatusStore;

/**
 * Otimiza (WebP) e grava a foto de uma concessionária fora do request de
 * upload -- `DealershipController::setPhoto()` só enfileira e devolve
 * `202` na hora; quem chamou acompanha o progresso via `JobStatusStore`
 * (`GET /jobs/{id}` ou `/events`, SSE).
 */
final readonly class ProcessDealershipPhotoJob implements Job
{
    public function __construct(
        private DealershipRepository $dealerships,
        private FileRepository $files,
        private StorageProvider $storage,
        private FileUploadService $uploads,
        private AuditLogger $audit,
        private JobStatusStore $jobStatus,
    ) {
    }

    public function handle(array $payload): void
    {
        $jobId = (string) $payload['job_id'];
        $dealershipId = (string) $payload['dealership_id'];
        $sourcePath = (string) $payload['source_path'];
        $originalName = (string) $payload['original_name'];
        $uploadedBy = $payload['uploaded_by'] !== null ? (string) $payload['uploaded_by'] : null;

        try {
            $dealership = $this->dealerships->findById($dealershipId);

            if (!$dealership instanceof Dealership) {
                $this->jobStatus->update($jobId, 'failed', 'failed', 100, ['error' => 'Dealership not found.']);

                return;
            }

            $this->jobStatus->update($jobId, 'processing', 'optimizing', 25);
            $file = $this->uploads->uploadImage($sourcePath, $originalName, $uploadedBy);

            $this->jobStatus->update($jobId, 'processing', 'saving', 75);
            $oldPhotoFileId = $dealership->photoFileId;
            $this->dealerships->update($dealership->withPhoto($file->id));
            $this->deleteStoredFile($oldPhotoFileId);

            $this->audit->record(AuditEvent::DealershipPhotoUpdated, $uploadedBy, 'Dealership', $dealership->id, [], '', null);

            $this->jobStatus->update($jobId, 'done', 'done', 100, [
                'result' => ['photo_url' => $this->storage->url($file->path)],
            ]);
        } catch (\Throwable $exception) {
            $this->jobStatus->update($jobId, 'failed', 'failed', 100, ['error' => $exception->getMessage()]);
        } finally {
            @unlink($sourcePath);
        }
    }

    /** Mesma ressalva de `DealershipController::deleteStoredFile()` -- aceita o risco teórico de dedupe por checksum. */
    private function deleteStoredFile(?string $fileId): void
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
