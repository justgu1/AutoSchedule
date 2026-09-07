<?php

declare(strict_types=1);

namespace App\Infrastructure\RateLimit;

use App\Infrastructure\Redis\RedisConnection;

/**
 * Sliding window counter, não fixed window (estoura na borda) nem sliding log (cresce sem limite).
 * Incremento e leitura num script Lua só, pra ser atômico sem round-trip extra.
 */
final readonly class RedisRateLimiter implements RateLimiter
{
    private const string SCRIPT = <<<'LUA'
        local current = redis.call('INCR', KEYS[1])
        if current == 1 then
            redis.call('EXPIRE', KEYS[1], ARGV[1] * 2)
        end
        local previous = tonumber(redis.call('GET', KEYS[2]) or '0')
        return {current, previous}
        LUA;

    public function __construct(private RedisConnection $connection)
    {
    }

    public function attempt(string $key, RateLimitPolicy $policy): RateLimitResult
    {
        $now = time();
        $windowId = intdiv($now, $policy->windowSeconds);
        $elapsedFraction = ($now % $policy->windowSeconds) / $policy->windowSeconds;

        [$current, $previous] = $this->connection->client()->eval(
            self::SCRIPT,
            2,
            "ratelimit:{$key}:{$windowId}",
            'ratelimit:' . $key . ':' . ($windowId - 1),
            $policy->windowSeconds,
        );

        $estimated = ((int) $previous * (1 - $elapsedFraction)) + (int) $current;

        return new RateLimitResult(
            allowed: $estimated <= $policy->maxAttempts,
            remaining: max(0, $policy->maxAttempts - (int) round($estimated)),
            resetSeconds: $policy->windowSeconds - ($now % $policy->windowSeconds),
        );
    }
}
