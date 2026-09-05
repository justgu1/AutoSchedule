<?php

declare(strict_types=1);

namespace App\Infrastructure\RateLimit;

use App\Infrastructure\Redis\RedisConnection;

/**
 * Sliding window counter: 2 contadores fixos (janela atual + anterior) com
 * interpolação, em vez de fixed window (estoura na borda de duas janelas) ou
 * sliding log (uma entrada por request, cresce sem limite). O incremento e a
 * leitura da janela anterior são um script Lua só -- atômico no Redis, sem
 * round-trip extra nem race entre requests concorrentes na mesma key.
 */
final class RedisRateLimiter implements RateLimiter
{
    private const SCRIPT = <<<'LUA'
        local current = redis.call('INCR', KEYS[1])
        if current == 1 then
            redis.call('EXPIRE', KEYS[1], ARGV[1] * 2)
        end
        local previous = tonumber(redis.call('GET', KEYS[2]) or '0')
        return {current, previous}
        LUA;

    public function __construct(private readonly RedisConnection $connection)
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

        // Peso da janela anterior cai conforme a janela atual avança -- é essa
        // interpolação que aproxima uma janela deslizante de verdade sem
        // guardar uma entrada por request.
        $estimated = ((int) $previous * (1 - $elapsedFraction)) + (int) $current;

        return new RateLimitResult(
            allowed: $estimated <= $policy->maxAttempts,
            remaining: max(0, $policy->maxAttempts - (int) round($estimated)),
            resetSeconds: $policy->windowSeconds - ($now % $policy->windowSeconds),
        );
    }
}
