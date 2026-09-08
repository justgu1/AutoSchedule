<?php

declare(strict_types=1);

namespace App\Application\Vehicle;

use App\Application\File\UploadFile;
use App\Application\Ports\JobProgress;
use App\Application\Ports\TempFileStore;
use App\Application\Ports\Transaction;
use App\Domain\Audit\AuditEntry;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\Ports\AuditLogger;
use App\Domain\Vehicle\Ports\VehicleImageRepository;
use App\Domain\Vehicle\Ports\VehicleRepository;
use App\Domain\Vehicle\Vehicle;
use App\Domain\Vehicle\VehicleImage;

/** Ver `EnqueueVehiclePhotos` pro motivo de isto rodar fora do request. */
final readonly class ProcessVehiclePhotos
{
    public function __construct(
        private VehicleRepository $vehicles,
        private VehicleImageRepository $images,
        private UploadFile $uploads,
        private AuditLogger $audit,
        private JobProgress $jobProgress,
        private TempFileStore $tempFiles,
        private Transaction $transaction,
    ) {
    }

    /** @param list<array{source_path: string, original_name: string}> $sources */
    public function __invoke(string $jobId, string $vehicleId, array $sources, ?string $uploadedBy): void
    {
        try {
            $vehicle = $this->vehicles->findById($vehicleId);

            if (!$vehicle instanceof Vehicle) {
                $this->jobProgress->update($jobId, 'failed', 'failed', 100, ['error' => 'Vehicle not found.']);

                return;
            }

            $total = count($sources);
            $added = 0;

            foreach ($sources as $index => $source) {
                $this->jobProgress->update($jobId, 'processing', sprintf('optimizing %d/%d', $index + 1, $total), (int) (($index / $total) * 100));

                // Uma transação por foto: uma imagem ruim no meio do lote não desfaz as que já entraram.
                $this->transaction->run(function () use ($vehicle, $source, $uploadedBy): void {
                    $file = $this->uploads->uploadImage($source['source_path'], $source['original_name'], $uploadedBy);
                    $this->images->insert(VehicleImage::register($vehicle->id, $file->id, $this->images->nextPosition($vehicle->id)));
                });

                ++$added;
            }

            $this->audit->record(new AuditEntry(AuditEvent::VehicleImagesAdded, $uploadedBy, $vehicle->id, ['added' => $added]));

            $this->jobProgress->update($jobId, 'done', 'done', 100, ['result' => ['added' => $added]]);
        } catch (\Throwable $exception) {
            // Quem acompanha é o cliente pelo `job_id`, não o worker: falha vira status, não retry.
            $this->jobProgress->update($jobId, 'failed', 'failed', 100, ['error' => $exception->getMessage()]);
        } finally {
            foreach ($sources as $source) {
                $this->tempFiles->discard($source['source_path']);
            }
        }
    }
}
