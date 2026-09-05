<?php

declare(strict_types=1);

namespace App\Infrastructure\RateLimit;

final readonly class RateLimitPolicy
{
    /** @param string $name identifica o "balde" de contagem -- rotas com policies diferentes (geral vs auth) nunca competem pela mesma cota */
    public function __construct(
        public string $name,
        public int $maxAttempts,
        public int $windowSeconds,
    ) {
    }
}
