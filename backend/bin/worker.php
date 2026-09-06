#!/usr/bin/env php
<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Application;
use App\Bootstrap\ContainerFactory;
use App\Domain\Ports\Job;
use App\Infrastructure\Queue\RedisQueue;

$app = new Application();
$container = ContainerFactory::build($app);
/** @var RedisQueue $queue */
$queue = $container->get(RedisQueue::class);

echo "Worker started.\n";

while (true) {
    $envelope = $queue->pop(timeoutSeconds: 5);

    if ($envelope === null) {
        continue;
    }

    try {
        /** @var Job $job */
        $job = $container->get($envelope['job_class']);
        $job->handle($envelope['payload']);
    } catch (\Throwable $exception) {
        echo sprintf("Job %s failed (attempt %d): %s\n", $envelope['job_class'], $envelope['attempts'] + 1, $exception->getMessage());
        $queue->retryOrFail($envelope);
    }
}
