<?php

declare(strict_types=1);

namespace App\Infrastructure\Jobs;

use App\Application\Ports\QueuedJob;

/** Único ponto que liga o nome do trabalho ao adapter que o executa. */
final class JobHandlers
{
    /** @return class-string<Job> */
    public static function for(QueuedJob $job): string
    {
        return match ($job) {
            QueuedJob::ProcessDealershipPhoto => ProcessDealershipPhotoJob::class,
            QueuedJob::ProcessVehiclePhotos => ProcessVehiclePhotosJob::class,
            QueuedJob::SendEmail => SendEmailJob::class,
        };
    }
}
