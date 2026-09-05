<?php

declare(strict_types=1);

namespace Tests\Infrastructure\RateLimit;

use App\Infrastructure\RateLimit\RateLimitPolicy;
use App\Infrastructure\RateLimit\RedisRateLimiter;
use App\Infrastructure\Redis\RedisConnection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Teste de integração: conecta no Redis real do docker-compose. Cada teste usa
 * uma key própria (uniqid) -- sem transação/rollback pra isolar (Redis não tem
 * isso), então nunca reaproveita key entre testes.
 */
final class RedisRateLimiterTest extends TestCase
{
    private RedisRateLimiter $limiter;

    protected function setUp(): void
    {
        $this->limiter = new RedisRateLimiter(new RedisConnection(
            host: getenv('REDIS_HOST') ?: '127.0.0.1',
            port: (int) (getenv('REDIS_PORT') ?: 6379),
            prefix: 'test',
        ));
    }

    #[Test]
    public function permite_enquanto_a_cota_nao_estoura(): void
    {
        $policy = new RateLimitPolicy('test', 3, 60);
        $key = 'permite-' . uniqid();

        $first = $this->limiter->attempt($key, $policy);
        $second = $this->limiter->attempt($key, $policy);

        $this->assertTrue($first->allowed);
        $this->assertTrue($second->allowed);
        $this->assertSame(2, $first->remaining);
        $this->assertSame(1, $second->remaining);
    }

    #[Test]
    public function bloqueia_depois_de_estourar_a_cota(): void
    {
        $policy = new RateLimitPolicy('test', 2, 60);
        $key = 'bloqueia-' . uniqid();

        $this->limiter->attempt($key, $policy);
        $this->limiter->attempt($key, $policy);
        $third = $this->limiter->attempt($key, $policy);

        $this->assertFalse($third->allowed);
        $this->assertSame(0, $third->remaining);
        $this->assertGreaterThan(0, $third->resetSeconds);
    }

    #[Test]
    public function keys_diferentes_tem_cotas_independentes(): void
    {
        $policy = new RateLimitPolicy('test', 1, 60);
        $keyA = 'independente-a-' . uniqid();
        $keyB = 'independente-b-' . uniqid();

        $this->limiter->attempt($keyA, $policy);
        $resultB = $this->limiter->attempt($keyB, $policy);

        $this->assertTrue($resultB->allowed);
    }
}
