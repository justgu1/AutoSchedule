<?php

declare(strict_types=1);

namespace App\Domain\Auth\Ports;

use App\Domain\Auth\OAuthClient;

interface OAuthClientRepository
{
    /** Client é seedado, não registrado em runtime ainda -- só leitura de propósito, sem insert/update/delete. */
    public function findByClientId(string $clientId): ?OAuthClient;
}
