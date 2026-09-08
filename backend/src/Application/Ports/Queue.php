<?php

declare(strict_types=1);

namespace App\Application\Ports;

interface Queue
{
    /** @param array<string, mixed> $payload */
    public function push(QueuedJob $job, array $payload): void;
}
