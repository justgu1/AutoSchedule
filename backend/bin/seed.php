#!/usr/bin/env php
<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Application;
use App\Infrastructure\Database\PostgresConnection;
use App\Infrastructure\Database\SeederRunner;

$app = new Application();
$config = $app->config('database');

$connection = new PostgresConnection(
    $config['driver'],
    $config['host'],
    $config['port'],
    $config['database'],
    $config['username'],
    $config['password'],
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
