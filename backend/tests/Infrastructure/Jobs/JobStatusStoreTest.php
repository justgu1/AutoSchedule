<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Jobs;

use App\Infrastructure\Jobs\JobStatusStore;
use App\Infrastructure\Redis\RedisConnection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** Teste de integração: conecta no Redis real do docker-compose, igual RedisQueueTest. */
final class JobStatusStoreTest extends TestCase
{
    private RedisConnection $connection;
    private JobStatusStore $store;
    private string $jobId;

    protected function setUp(): void
    {
        $this->connection = new RedisConnection(
            host: getenv('REDIS_HOST') ?: '127.0.0.1',
            port: (int) (getenv('REDIS_PORT') ?: 6379),
        );
        $this->store = new JobStatusStore($this->connection);
        $this->jobId = 'test-' . bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        $this->connection->client()->del('job_status:' . $this->jobId);
    }

    #[Test]
    public function create_grava_o_job_como_queued_sem_progresso(): void
    {
        $this->store->create($this->jobId);

        $this->assertSame(['status' => 'queued', 'step' => 'queued', 'progress' => 0], $this->store->get($this->jobId));
    }

    #[Test]
    public function update_sobrescreve_o_status_anterior_e_aceita_dados_extras(): void
    {
        $this->store->create($this->jobId);

        $this->store->update($this->jobId, 'done', 'done', 100, ['result' => ['photo_url' => 'https://example.com/x.webp']]);

        $this->assertSame(
            ['status' => 'done', 'step' => 'done', 'progress' => 100, 'result' => ['photo_url' => 'https://example.com/x.webp']],
            $this->store->get($this->jobId),
        );
    }

    #[Test]
    public function get_de_um_job_que_nunca_existiu_devolve_null(): void
    {
        $this->assertNull($this->store->get('never-existed-' . bin2hex(random_bytes(8))));
    }
}
