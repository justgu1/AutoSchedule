#!/usr/bin/env php
<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Application;
use App\Bootstrap\ContainerFactory;
use App\Infrastructure\Scheduler\Scheduler;

$app = new Application();
$container = ContainerFactory::build($app);

echo "Scheduler started.\n";

$container->get(Scheduler::class)->loop();
