<?php

declare(strict_types=1);

namespace App\Application\Ports;

interface Queue
{
    /**
     * @param class-string<Job> $jobClass
     * @param array<string, mixed> $payload
     */
    public function push(string $jobClass, array $payload): void;
}
