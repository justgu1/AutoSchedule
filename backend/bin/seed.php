#!/usr/bin/env php
<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Config;
use App\Infrastructure\Database\PostgresConnection;
use App\Infrastructure\Database\SeederRunner;

$app = new Config();
$connection = new PostgresConnection(
    $app->string('database.driver'),
    $app->string('database.host'),
    $app->int('database.port'),
    $app->string('database.database'),
    $app->string('database.username'),
    $app->string('database.password'),
);

$runner = new SeederRunner($connection->pdo(), dirname(__DIR__) . '/database/seeders');
$names = $runner->run();

if ($names === []) {
    echo "No seeders found.\n";

    exit(0);
}

foreach ($names as $name) {
    echo 'Seeded: ' . $name . "\n";
}
