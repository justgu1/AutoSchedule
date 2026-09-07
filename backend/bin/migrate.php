#!/usr/bin/env php
<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Config;
use App\Infrastructure\Database\MigrationRunner;
use App\Infrastructure\Database\PostgresConnection;

$app = new Config();
$connection = new PostgresConnection(
    $app->string('database.driver'),
    $app->string('database.host'),
    $app->int('database.port'),
    $app->string('database.database'),
    $app->string('database.username'),
    $app->string('database.password'),
);

$runner = new MigrationRunner($connection->pdo(), dirname(__DIR__) . '/database/migrations');

$rollback = ($argv[1] ?? null) === '--rollback';
$names = $rollback ? $runner->rollback() : $runner->run();

if ($names === []) {
    echo $rollback ? "Nothing to rollback.\n" : "Nothing to migrate.\n";

    exit(0);
}

foreach ($names as $name) {
    echo ($rollback ? 'Rolled back: ' : 'Migrated: ') . $name . "\n";
}
