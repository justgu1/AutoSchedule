#!/usr/bin/env php
<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Bootstrap\CliKernel;
use App\Infrastructure\Persistence\SeederRunner;

$names = new SeederRunner(
    CliKernel::boot()->maintenanceConnection()->pdo(),
    dirname(__DIR__) . '/database/seeders',
)->run();

if ($names === []) {
    echo "No seeders found.\n";

    exit(0);
}

foreach ($names as $name) {
    echo 'Seeded: ' . $name . "\n";
}
