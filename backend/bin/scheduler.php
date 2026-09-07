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
$container->get(DatabaseConnection::class)->pdo()->exec("SET app.is_service_context = 'true'");

echo "Scheduler started.\n";

$container->get(Scheduler::class)->loop();
