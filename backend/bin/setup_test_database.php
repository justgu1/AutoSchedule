#!/usr/bin/env php
<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Bootstrap\CliKernel;

/** Cada banco irmão isola teste/E2E do de dev; `CREATE DATABASE` conecta na `postgres` porque sempre existe. */
const SIBLING_DATABASES = ['autoschedule_test', 'autoschedule_e2e'];

$database = $argv[1] ?? 'autoschedule_test';
$fresh = ($argv[2] ?? null) === '--fresh';

// Allowlist fixa, nunca input externo livre: `CREATE DATABASE`/`DROP DATABASE` não aceitam bind,
// e interpolar um argv sem checar seria injeção de verdade.
if (!in_array($database, SIBLING_DATABASES, true)) {
    fwrite(STDERR, sprintf("Banco '%s' não é um banco irmão conhecido (%s).\n", $database, implode(', ', SIBLING_DATABASES)));

    exit(1);
}

$pdo = CliKernel::boot()->maintenanceConnection('postgres')->pdo();

$statement = $pdo->prepare('SELECT 1 FROM pg_database WHERE datname = ?');
$statement->execute([$database]);
$exists = (bool) $statement->fetchColumn();

// `--fresh` (só o E2E usa): teste real commita de verdade, sem rollback de transação de teste --
// sem recriar do zero a cada rodada, o banco só cresce e um dia gera falha por acúmulo, não por bug.
if ($exists && $fresh) {
    $pdo->exec("DROP DATABASE {$database} WITH (FORCE)");
    $exists = false;
    echo 'Dropped ' . $database . ".\n";
}

if ($exists) {
    echo $database . " already exists.\n";

    exit(0);
}

$pdo->exec('CREATE DATABASE ' . $database);
echo 'Created ' . $database . ".\n";
