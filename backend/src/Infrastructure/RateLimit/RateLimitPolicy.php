<?php

declare(strict_types=1);

namespace App\Infrastructure\RateLimit;

final class RateLimitPolicy
{
    /** @param string $name identifica o "balde" de contagem -- rotas com policies diferentes (geral vs auth) nunca competem pela mesma cota */
    public function __construct(
        public readonly string $name,
        public readonly int $maxAttempts,
        public readonly int $windowSeconds,
    ) {
    }
}
