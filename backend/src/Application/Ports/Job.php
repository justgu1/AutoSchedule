<?php

declare(strict_types=1);

namespace App\Application\Ports;

/** Caso de uso com outro gatilho: fila em vez de HTTP. */
interface Job
{
    /** @param array<string, mixed> $payload */
    public function handle(array $payload): void;
}
