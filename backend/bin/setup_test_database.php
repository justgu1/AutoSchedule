#!/usr/bin/env php
<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Bootstrap\CliKernel;

/** Cada banco irmão isola teste/E2E do de dev; `CREATE DATABASE` conecta na `postgres` porque sempre existe. */
const SIBLING_DATABASES = ['autoschedule_test', 'autoschedule_e2e'];

$database = $argv[1] ?? 'autoschedule_test';

// Allowlist fixa, nunca input externo livre: `CREATE DATABASE` não aceita bind, e interpolar um
// argv sem checar seria injeção de verdade.
if (!in_array($database, SIBLING_DATABASES, true)) {
    fwrite(STDERR, sprintf("Banco '%s' não é um banco irmão conhecido (%s).\n", $database, implode(', ', SIBLING_DATABASES)));

    exit(1);
}

$pdo = CliKernel::boot()->maintenanceConnection('postgres')->pdo();

$statement = $pdo->prepare('SELECT 1 FROM pg_database WHERE datname = ?');
$statement->execute([$database]);

if ((bool) $statement->fetchColumn()) {
    echo $database . " already exists.\n";

    exit(0);
}

$pdo->exec('CREATE DATABASE ' . $database);
echo 'Created ' . $database . ".\n";
