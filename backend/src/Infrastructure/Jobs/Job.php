<?php

declare(strict_types=1);

namespace App\Infrastructure\Jobs;

/** Adapter entre o envelope da fila e um caso de uso: traduz payload, não decide nada. */
interface Job
{
    /** @param array<string, mixed> $payload */
    public function handle(array $payload): void;
}
