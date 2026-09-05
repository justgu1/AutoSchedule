<?php

declare(strict_types=1);

namespace App\Infrastructure\RateLimit;

interface RateLimiter
{
    /** Registra uma tentativa pra $key sob $policy e devolve se ainda cabe na cota. */
    public function attempt(string $key, RateLimitPolicy $policy): RateLimitResult;
}
