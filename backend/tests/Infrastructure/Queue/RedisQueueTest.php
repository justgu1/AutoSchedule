<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Queue;

use App\Infrastructure\Queue\RedisQueue;
use App\Infrastructure\Redis\RedisConnection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Teste de integração: conecta no Redis real do docker-compose, igual
 * RedisConnectionTest/RedisRateLimiterTest.
 */
final class RedisQueueTest extends TestCase
{
    private RedisConnection $connection;
    private RedisQueue $queue;

    protected function setUp(): void
    {
        $this->connection = new RedisConnection(
            host: getenv('REDIS_HOST') ?: '127.0.0.1',
            port: (int) (getenv('REDIS_PORT') ?: 6379),
        );
        $this->queue = new RedisQueue($this->connection);
    }

    protected function tearDown(): void
    {
        $this->connection->client()->del('jobs:default', 'jobs:failed');
    }

    #[Test]
    public function push_e_pop_entregam_o_mesmo_job(): void
    {
        $this->queue->push('SomeJob', ['a' => 1]);

        $envelope = $this->queue->pop(timeoutSeconds: 2);
        assert($envelope !== null);

        $this->assertSame('SomeJob', $envelope['job_class']);
        $this->assertSame(['a' => 1], $envelope['payload']);
        $this->assertSame(0, $envelope['attempts']);
    }

    #[Test]
    public function pop_devolve_null_quando_a_fila_esta_vazia(): void
    {
        $this->assertNull($this->queue->pop(timeoutSeconds: 1));
    }

    #[Test]
    public function retry_or_fail_reenfileira_com_attempts_incrementado(): void
    {
        $this->queue->push('SomeJob', []);
        $envelope = $this->queue->pop(timeoutSeconds: 2);
        assert($envelope !== null);

        $this->queue->retryOrFail($envelope);

        $requeued = $this->queue->pop(timeoutSeconds: 2);
        assert($requeued !== null);
        $this->assertSame(1, $requeued['attempts']);
    }

    #[Test]
    public function retry_or_fail_manda_pra_lista_de_falhas_apos_o_maximo_de_tentativas(): void
    {
        $this->queue->push('SomeJob', []);
        $envelope = $this->queue->pop(timeoutSeconds: 2);
        assert($envelope !== null);

        for ($i = 0; $i < 2; $i++) {
            $this->queue->retryOrFail($envelope);
            $envelope = $this->queue->pop(timeoutSeconds: 2);
            assert($envelope !== null);
        }

        $this->queue->retryOrFail($envelope);

        $this->assertNull($this->queue->pop(timeoutSeconds: 1));
        $this->assertCount(1, $this->connection->client()->lrange('jobs:failed', 0, -1));
    }
}
