<?php

declare(strict_types=1);

namespace App\Infrastructure\Jobs;

use App\Application\Shared\ValidatedInput;
use App\Application\Vehicle\ProcessVehiclePhotos;

final readonly class ProcessVehiclePhotosJob implements Job
{
    public function __construct(private ProcessVehiclePhotos $processPhotos)
    {
    }

    public function handle(array $payload): void
    {
        $input = new ValidatedInput($payload);

        ($this->processPhotos)(
            jobId: $input->string('job_id'),
            vehicleId: $input->string('vehicle_id'),
            sources: $this->sources($payload['sources'] ?? null),
            uploadedBy: $input->stringOrNull('uploaded_by'),
        );
    }

    /**
     * O envelope veio de JSON, então a lista é `mixed` até aqui -- é este o lugar de conferir a forma.
     *
     * @return list<array{source_path: string, original_name: string}>
     */
    private function sources(mixed $raw): array
    {
        $sources = [];

        foreach (is_array($raw) ? $raw : [] as $source) {
            if (!is_array($source)) {
                continue;
            }

            $input = new ValidatedInput(array_filter($source, is_string(...), ARRAY_FILTER_USE_KEY));
            $sources[] = ['source_path' => $input->string('source_path'), 'original_name' => $input->string('original_name')];
        }

        return $sources;
    }
}
