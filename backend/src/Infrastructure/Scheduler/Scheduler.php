<?php

declare(strict_types=1);

namespace App\Infrastructure\Scheduler;

use App\Domain\Ports\ScheduledTask;
use App\Infrastructure\Redis\RedisConnection;

final readonly class Scheduler
{
    /** @param list<ScheduledTask> $tasks */
    public function __construct(
        private RedisConnection $redis,
        private array $tasks,
    ) {
    }

    public function tick(\DateTimeImmutable $now): void
    {
        foreach ($this->tasks as $task) {
            $key = "scheduler:last_run:{$task->name()}";
            $lastRunAt = $this->redis->client()->get($key);

            if ($lastRunAt !== null && ($now->getTimestamp() - (int) $lastRunAt) < $task->dueIntervalSeconds()) {
                continue;
            }

            $task->run();
            $this->redis->client()->set($key, (string) $now->getTimestamp());
        }
    }

    public function loop(int $tickIntervalSeconds = 60): never
    {
        while (true) {
            $this->tick(new \DateTimeImmutable());
            sleep($tickIntervalSeconds);
        }
    }
}
