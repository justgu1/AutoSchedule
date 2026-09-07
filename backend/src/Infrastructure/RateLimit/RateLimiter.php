<?php

declare(strict_types=1);

namespace App\Infrastructure\RateLimit;

interface RateLimiter
{
    public function attempt(string $key, RateLimitPolicy $policy): RateLimitResult;
}
