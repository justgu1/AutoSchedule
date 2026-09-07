<?php

declare(strict_types=1);

namespace App\Application\Dealership;

use App\Application\File\UploadFile;
use App\Application\Ports\Job;
use App\Application\Ports\JobProgress;
use App\Application\Ports\TempFileStore;
use App\Application\Shared\ValidatedInput;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\Ports\AuditLogger;
use App\Domain\Dealership\Dealership;
use App\Domain\Dealership\Ports\DealershipRepository;
use App\Domain\File\Ports\StorageProvider;

/** Ver `EnqueueDealershipPhoto` pro motivo de isto rodar fora do request. */
final readonly class ProcessDealershipPhotoJob implements Job
{
    public function __construct(
        private DealershipRepository $dealerships,
        private DealershipPhotos $photos,
        private StorageProvider $storage,
        private UploadFile $uploads,
        private AuditLogger $audit,
        private JobProgress $jobProgress,
        private TempFileStore $tempFiles,
    ) {
    }

    public function handle(array $payload): void
    {
        $input = new ValidatedInput($payload);
        $jobId = $input->string('job_id');
        $dealershipId = $input->string('dealership_id');
        $sourcePath = $input->string('source_path');
        $originalName = $input->string('original_name');
        $uploadedBy = $input->stringOrNull('uploaded_by');

        try {
            $dealership = $this->dealerships->findById($dealershipId);

            if (!$dealership instanceof Dealership) {
                $this->jobProgress->update($jobId, 'failed', 'failed', 100, ['error' => 'Dealership not found.']);

                return;
            }

            $this->jobProgress->update($jobId, 'processing', 'optimizing', 25);
            $file = $this->uploads->uploadImage($sourcePath, $originalName, $uploadedBy);

            $this->jobProgress->update($jobId, 'processing', 'saving', 75);
            $oldPhotoFileId = $dealership->photoFileId;
            $this->dealerships->update($dealership->withPhoto($file->id));
            $this->photos->delete($oldPhotoFileId);

            $this->audit->record(AuditEvent::DealershipPhotoUpdated, $uploadedBy, 'Dealership', $dealership->id, [], null, null);

            $this->jobProgress->update($jobId, 'done', 'done', 100, [
                'result' => ['photo_url' => $this->storage->url($file->path)],
            ]);
        } catch (\Throwable $exception) {
            $this->jobProgress->update($jobId, 'failed', 'failed', 100, ['error' => $exception->getMessage()]);
        } finally {
            $this->tempFiles->discard($sourcePath);
        }
    }
}
