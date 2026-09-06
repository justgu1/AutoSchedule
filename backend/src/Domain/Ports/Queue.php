<?php

declare(strict_types=1);

namespace App\Domain\Ports;

interface Queue
{
    /** @param array<string, mixed> $payload */
    public function push(string $jobClass, array $payload): void;
}
