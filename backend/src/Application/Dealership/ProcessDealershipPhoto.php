<?php

declare(strict_types=1);

namespace App\Application\Dealership;

use App\Application\File\UploadFile;
use App\Application\Ports\JobProgress;
use App\Application\Ports\TempFileStore;
use App\Application\Ports\Transaction;
use App\Domain\Audit\AuditEntry;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\Ports\AuditLogger;
use App\Domain\Dealership\Dealership;
use App\Domain\Dealership\Ports\DealershipRepository;
use App\Domain\File\Ports\StorageProvider;
use App\Domain\File\StoredFile;

/** Ver `EnqueueDealershipPhoto` pro motivo de isto rodar fora do request. */
final readonly class ProcessDealershipPhoto
{
    public function __construct(
        private DealershipRepository $dealerships,
        private DealershipPhotos $photos,
        private StorageProvider $storage,
        private UploadFile $uploads,
        private AuditLogger $audit,
        private JobProgress $jobProgress,
        private TempFileStore $tempFiles,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(
        string $jobId,
        string $dealershipId,
        string $sourcePath,
        string $originalName,
        ?string $uploadedBy,
    ): void {
        try {
            $dealership = $this->dealerships->findById($dealershipId);

            if (!$dealership instanceof Dealership) {
                $this->jobProgress->update($jobId, 'failed', 'failed', 100, ['error' => 'Dealership not found.']);

                return;
            }

            $this->jobProgress->update($jobId, 'processing', 'optimizing', 25);

            // Fora do request não há transação nenhuma: sem isto, falhar no meio deixa arquivo gravado e foto não trocada.
            $file = $this->transaction->run(function () use ($dealership, $sourcePath, $originalName, $uploadedBy, $jobId): StoredFile {
                $file = $this->uploads->uploadImage($sourcePath, $originalName, $uploadedBy);

                $this->jobProgress->update($jobId, 'processing', 'saving', 75);
                $oldPhotoFileId = $dealership->photoFileId;
                $this->dealerships->update($dealership->withPhoto($file->id));
                $this->photos->delete($oldPhotoFileId);

                return $file;
            });

            $this->audit->record(new AuditEntry(AuditEvent::DealershipPhotoUpdated, $uploadedBy, $dealership->id));

            $this->jobProgress->update($jobId, 'done', 'done', 100, [
                'result' => ['photo_url' => $this->storage->url($file->path)],
            ]);
        } catch (\Throwable $exception) {
            // Quem acompanha é o cliente pelo `job_id`, não o worker: falha vira status, não retry.
            $this->jobProgress->update($jobId, 'failed', 'failed', 100, ['error' => $exception->getMessage()]);
        } finally {
            $this->tempFiles->discard($sourcePath);
        }
    }
}
