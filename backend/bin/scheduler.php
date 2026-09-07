#!/usr/bin/env php
<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Bootstrap\CliKernel;
use App\Infrastructure\Scheduler\Scheduler;

$kernel = CliKernel::boot();
$kernel->enterServiceContext();

echo "Scheduler started.\n";

$kernel->container->get(Scheduler::class)->loop();
