#!/usr/bin/env php
<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Application\Ports\Job;
use App\Bootstrap\CliKernel;
use App\Infrastructure\Queue\RedisQueue;

$kernel = CliKernel::boot();
$kernel->enterServiceContext();

$queue = $kernel->container->get(RedisQueue::class);

echo "Worker started.\n";

while (true) {
    $envelope = $queue->pop(timeoutSeconds: 5);

    if ($envelope === null) {
        continue;
    }

    try {
        /** @var Job $job */
        $job = $kernel->container->get($envelope['job_class']);
        $job->handle($envelope['payload']);
    } catch (\Throwable $exception) {
        echo sprintf("Job %s failed (attempt %d): %s\n", $envelope['job_class'], $envelope['attempts'] + 1, $exception->getMessage());
        $queue->retryOrFail($envelope);
    }
}
