#!/usr/bin/env php
<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Bootstrap\CliKernel;

/**
 * Sessão manual ou E2E contra o banco de dev deixa refresh token real que quebra teste do usuário seedado.
 * `CREATE DATABASE` não roda de dentro do próprio banco, daí conectar na `postgres`, que sempre existe.
 */
const TEST_DATABASE = 'autoschedule_test';

$pdo = CliKernel::boot()->maintenanceConnection('postgres')->pdo();

$statement = $pdo->prepare('SELECT 1 FROM pg_database WHERE datname = ?');
$statement->execute([TEST_DATABASE]);

if ((bool) $statement->fetchColumn()) {
    echo TEST_DATABASE . " already exists.\n";

    exit(0);
}

// Nome fixo, nunca input externo: CREATE DATABASE não aceita bind, e interpolar aqui não é injeção.
$pdo->exec('CREATE DATABASE ' . TEST_DATABASE);
echo 'Created ' . TEST_DATABASE . ".\n";
