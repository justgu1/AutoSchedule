<?php

declare(strict_types=1);

namespace App\Infrastructure\Queue;

use App\Application\Ports\Queue;
use App\Application\Ports\QueuedJob;
use App\Infrastructure\Redis\RedisConnection;

final readonly class RedisQueue implements Queue
{
    private const string QUEUE_KEY = 'jobs:default';
    private const string FAILED_KEY = 'jobs:failed';
    private const int MAX_ATTEMPTS = 3;

    public function __construct(private RedisConnection $redis)
    {
    }

    public function push(QueuedJob $job, array $payload): void
    {
        $this->redis->client()->rpush(self::QUEUE_KEY, [new QueueMessage($job, $payload)->toJson()]);
    }

    public function pop(int $timeoutSeconds): ?QueueMessage
    {
        $result = $this->redis->client()->blpop([self::QUEUE_KEY], $timeoutSeconds);

        return is_array($result) && is_string($result[1]) ? QueueMessage::fromJson($result[1]) : null;
    }

    public function retryOrFail(QueueMessage $message): void
    {
        $retried = $message->retried();
        $key = $retried->attempts >= self::MAX_ATTEMPTS ? self::FAILED_KEY : self::QUEUE_KEY;

        $this->redis->client()->rpush($key, [$retried->toJson()]);
    }
}
