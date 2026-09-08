#!/usr/bin/env php
<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Bootstrap\CliKernel;
use App\Infrastructure\Jobs\JobHandlers;
use App\Infrastructure\Queue\RedisQueue;

$kernel = CliKernel::boot();
$kernel->enterServiceContext();

$queue = $kernel->container->get(RedisQueue::class);

echo "Worker started.\n";

while (true) {
    $message = $queue->pop(timeoutSeconds: 5);

    if ($message === null) {
        continue;
    }

    try {
        $kernel->container->get(JobHandlers::for($message->job))->handle($message->payload);
    } catch (\Throwable $exception) {
        echo sprintf("Job %s failed (attempt %d): %s\n", $message->job->value, $message->attempts + 1, $exception->getMessage());
        $queue->retryOrFail($message);
    }
}
