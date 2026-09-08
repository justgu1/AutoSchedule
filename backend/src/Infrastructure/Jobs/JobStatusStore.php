<?php

declare(strict_types=1);

namespace App\Infrastructure\Jobs;

use App\Application\Ports\JobProgress;
use App\Infrastructure\Redis\RedisConnection;

/**
 * TTL porque progresso de job antigo não interessa a ninguém e acumularia pra sempre.
 */
final readonly class JobStatusStore implements JobProgress
{
    private const int TTL_SECONDS = 3600;

    public function __construct(private RedisConnection $redis)
    {
    }

    public function create(string $jobId): void
    {
        $this->write($jobId, ['status' => 'queued', 'step' => 'queued', 'progress' => 0]);
    }

    /** @param array<string, mixed> $extra */
    public function update(string $jobId, string $status, string $step, int $progress, array $extra = []): void
    {
        $this->write($jobId, ['status' => $status, 'step' => $step, 'progress' => $progress, ...$extra]);
    }

    /** @return array<string, mixed>|null */
    public function get(string $jobId): ?array
    {
        $raw = $this->redis->client()->get($this->key($jobId));

        if (!is_string($raw)) {
            return null;
        }

        /** @var array<string, mixed> */
        return json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
    }

    /** @param array<string, mixed> $data */
    private function write(string $jobId, array $data): void
    {
        $this->redis->client()->setex($this->key($jobId), self::TTL_SECONDS, json_encode($data, JSON_THROW_ON_ERROR));
    }

    private function key(string $jobId): string
    {
        return 'job_status:' . $jobId;
    }
}
