<?php

declare(strict_types=1);

namespace App\Application\Ports;

/**
 * Progresso que um job conta de si mesmo enquanto roda, pra quem enfileirou
 * poder acompanhar -- não é o estado da fila em si (isso é `Queue`).
 */
interface JobProgress
{
    public function create(string $jobId): void;

    /** @param array<string, mixed> $extra */
    public function update(string $jobId, string $status, string $step, int $progress, array $extra = []): void;

    /** @return array<string, mixed>|null */
    public function get(string $jobId): ?array;
}
