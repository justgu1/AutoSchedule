#!/usr/bin/env php
<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Bootstrap\CliKernel;
use App\Infrastructure\Database\MigrationRunner;

$runner = new MigrationRunner(
    CliKernel::boot()->maintenanceConnection()->pdo(),
    dirname(__DIR__) . '/database/migrations',
);

$rollback = ($argv[1] ?? null) === '--rollback';
$names = $rollback ? $runner->rollback() : $runner->run();

if ($names === []) {
    echo $rollback ? "Nothing to rollback.\n" : "Nothing to migrate.\n";

    exit(0);
}

foreach ($names as $name) {
    echo ($rollback ? 'Rolled back: ' : 'Migrated: ') . $name . "\n";
}
