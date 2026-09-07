#!/usr/bin/env php
<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Application;
use App\Bootstrap\ContainerFactory;
use App\Domain\Ports\DatabaseConnection;
use App\Domain\Ports\Job;
use App\Infrastructure\Queue\RedisQueue;

$app = new Application();
$container = ContainerFactory::build($app);
/** @var RedisQueue $queue */
$queue = $container->get(RedisQueue::class);

// Sem isso, RLS esconde toda linha de `users`/`dealerships` das queries que
// os jobs fazem -- não tem request HTTP aqui, então `current_user_id`/`role`
// nunca são setados; a policy de serviço (mesma usada por login/registro) é
// o único jeito de um processo em background enxergar qualquer linha.
// `SET` (não `SET LOCAL`) porque essa conexão vive pelo processo inteiro, sem
// transação por job.
//
// Retry porque este processo pode subir antes da migration que cria a role
// `autoschedule_app` (ou antes do Postgres aceitar conexões) -- sem loop,
// crasha de vez e nunca mais processa fila nenhuma.
$maxAttempts = 30;
for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
    try {
        $container->get(DatabaseConnection::class)->pdo()->exec("SET app.is_service_context = 'true'");

        break;
    } catch (\PDOException $exception) {
        if ($attempt === $maxAttempts) {
            throw $exception;
        }

        echo sprintf("Aguardando banco de dados (tentativa %d/%d): %s\n", $attempt, $maxAttempts, $exception->getMessage());
        sleep(1);
    }
}

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
