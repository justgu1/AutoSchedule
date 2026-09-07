<?php

declare(strict_types=1);

namespace App\Application\Ports;

/** O que o job conta de si mesmo enquanto roda; o estado da fila em si é `Queue`. */
interface JobProgress
{
    public function create(string $jobId): void;

    /** @param array<string, mixed> $extra */
    public function update(string $jobId, string $status, string $step, int $progress, array $extra = []): void;

    /** @return array<string, mixed>|null */
    public function get(string $jobId): ?array;
}
