<?php

declare(strict_types=1);

namespace App\Infrastructure\Jobs;

use App\Application\Dealership\ProcessDealershipPhoto;
use App\Application\Shared\ValidatedInput;

final readonly class ProcessDealershipPhotoJob implements Job
{
    public function __construct(private ProcessDealershipPhoto $processPhoto)
    {
    }

    public function handle(array $payload): void
    {
        $input = new ValidatedInput($payload);

        ($this->processPhoto)(
            jobId: $input->string('job_id'),
            dealershipId: $input->string('dealership_id'),
            sourcePath: $input->string('source_path'),
            originalName: $input->string('original_name'),
            uploadedBy: $input->stringOrNull('uploaded_by'),
        );
    }
}
