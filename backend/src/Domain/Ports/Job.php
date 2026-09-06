<?php

declare(strict_types=1);

namespace App\Domain\Ports;

interface Job
{
    /** @param array<string, mixed> $payload */
    public function handle(array $payload): void;
}
