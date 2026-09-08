<?php

declare(strict_types=1);

namespace App\Application\Vehicle;

use App\Application\Ports\JobProgress;
use App\Application\Ports\Queue;
use App\Application\Ports\QueuedJob;
use App\Application\Ports\TempFileStore;
use App\Application\Shared\ActorContext;
use App\Application\Shared\UploadLimits;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Domain\Shared\Uuid;
use App\Domain\Vehicle\Ports\VehicleImageRepository;

/**
 * Um `job_id` pro lote inteiro: N jobs exigiriam N conexões SSE do mesmo cliente,
 * e o navegador corta em 6 por host.
 */
final readonly class EnqueueVehiclePhotos
{
    private const int MAX_PHOTOS_PER_REQUEST = 10;
    private const int MAX_GALLERY_SIZE = 20;

    public function __construct(
        private VehicleFinder $finder,
        private VehicleImageRepository $images,
        private TempFileStore $tempFiles,
        private JobProgress $jobProgress,
        private Queue $queue,
    ) {
    }

    /**
     * @param list<array{tmp_path: string, original_name: string, size_bytes: int}> $uploads
     * @return string o `job_id` que acompanha o lote
     */
    public function __invoke(?string $id, array $uploads, ActorContext $context): string
    {
        $vehicle = $this->finder->findOrFail($id);
        $this->assertFits($vehicle->id, $uploads);

        $jobId = Uuid::v7();
        // Estagia tudo antes de enfileirar: o worker roda em outro processo e não enxerga o tmp do PHP.
        $sources = array_map(fn (array $upload): array => [
            'source_path' => $this->tempFiles->stage($upload['tmp_path'], Uuid::v7()),
            'original_name' => $upload['original_name'],
        ], $uploads);

        $this->jobProgress->create($jobId);
        $this->queue->push(QueuedJob::ProcessVehiclePhotos, [
            'job_id' => $jobId,
            'vehicle_id' => $vehicle->id,
            'sources' => $sources,
            'uploaded_by' => $context->actorId,
        ]);

        return $jobId;
    }

    /**
     * Recusa antes de copiar: o que vai ser rejeitado no worker não vale a viagem.
     *
     * @param list<array{tmp_path: string, original_name: string, size_bytes: int}> $uploads
     */
    private function assertFits(string $vehicleId, array $uploads): void
    {
        if ($uploads === []) {
            throw new DomainException('Invalid data.', DomainErrorType::Validation, ['images' => 'At least one image is required.']);
        }

        if (count($uploads) > self::MAX_PHOTOS_PER_REQUEST) {
            throw new DomainException('Invalid data.', DomainErrorType::Validation, [
                'images' => sprintf('At most %d images can be sent at once.', self::MAX_PHOTOS_PER_REQUEST),
            ]);
        }

        foreach ($uploads as $upload) {
            if ($upload['size_bytes'] > UploadLimits::MAX_IMAGE_BYTES) {
                throw new DomainException('Invalid data.', DomainErrorType::Validation, [
                    'images' => sprintf('Each image must be at most %dMB.', intdiv(UploadLimits::MAX_IMAGE_BYTES, 1024 * 1024)),
                ]);
            }
        }

        // Contagem de linhas não cabe em CHECK de tabela, então o teto da galeria só existe aqui.
        if (count($this->images->findByVehicle($vehicleId)) + count($uploads) > self::MAX_GALLERY_SIZE) {
            throw new DomainException('Invalid data.', DomainErrorType::Validation, [
                'images' => sprintf('A vehicle can have at most %d images.', self::MAX_GALLERY_SIZE),
            ]);
        }
    }
}
