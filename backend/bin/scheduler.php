#!/usr/bin/env php
<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Application;
use App\Bootstrap\ContainerFactory;
use App\Domain\Ports\DatabaseConnection;
use App\Infrastructure\Scheduler\Scheduler;

$app = new Application();
$container = ContainerFactory::build($app);

// Mesma razão do bin/worker.php: sem request HTTP, sem `current_user_id`/role
// -- só a policy de serviço deixa o purge agendado enxergar qualquer linha.
// Retry: ver comentário equivalente em bin/worker.php.
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

echo "Scheduler started.\n";

$container->get(Scheduler::class)->loop();
