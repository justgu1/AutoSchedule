<?php

declare(strict_types=1);

namespace App\Infrastructure\Queue;

use App\Domain\Ports\Queue;
use App\Infrastructure\Redis\RedisConnection;

final readonly class RedisQueue implements Queue
{
    private const string QUEUE_KEY = 'jobs:default';
    private const string FAILED_KEY = 'jobs:failed';
    private const int MAX_ATTEMPTS = 3;

    public function __construct(private RedisConnection $redis)
    {
    }

    public function push(string $jobClass, array $payload): void
    {
        $this->redis->client()->rpush(self::QUEUE_KEY, [$this->encode($jobClass, $payload, attempts: 0)]);
    }

    /** @return array{job_class: string, payload: array<string, mixed>, attempts: int}|null */
    public function pop(int $timeoutSeconds): ?array
    {
        $result = $this->redis->client()->blpop([self::QUEUE_KEY], $timeoutSeconds);

        if ($result === null) {
            return null;
        }

        /** @var array{job_class: string, payload: array<string, mixed>, attempts: int} */
        return json_decode($result[1], true);
    }

    /** @param array{job_class: string, payload: array<string, mixed>, attempts: int} $envelope */
    public function retryOrFail(array $envelope): void
    {
        $envelope['attempts']++;
        $key = $envelope['attempts'] >= self::MAX_ATTEMPTS ? self::FAILED_KEY : self::QUEUE_KEY;

        $this->redis->client()->rpush($key, [$this->encode($envelope['job_class'], $envelope['payload'], $envelope['attempts'])]);
    }

    /** @param array<string, mixed> $payload */
    private function encode(string $jobClass, array $payload, int $attempts): string
    {
        return json_encode(['job_class' => $jobClass, 'payload' => $payload, 'attempts' => $attempts], JSON_THROW_ON_ERROR);
    }
}
