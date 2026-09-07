<?php

declare(strict_types=1);

namespace App\Application\Ports;

/**
 * Caso de uso disparado pela fila em vez de por uma request HTTP -- mesma
 * camada, outro gatilho.
 */
interface Job
{
    /** @param array<string, mixed> $payload */
    public function handle(array $payload): void;
}
