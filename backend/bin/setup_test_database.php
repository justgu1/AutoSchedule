#!/usr/bin/env php
<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Config;

/**
 * `phpunit` nunca pode rodar contra o banco de dev de verdade -- sessão
 * manual/E2E ali deixa `oauth_refresh_tokens` reais que quebram testes que
 * mexem no usuário seedado (RLS/seeders assumem estado limpo). `autoschedule_test`
 * é um banco irmão, no mesmo servidor Postgres, só pra isso -- criado uma
 * vez, migrado/seedado de novo a cada `make test` (idempotente, ver
 * `MigrationRunner`/`SeederRunner`).
 *
 * `CREATE DATABASE` não roda dentro de transação nem de dentro do próprio
 * banco -- conecta na `postgres` (banco de manutenção, sempre existe) com a
 * mesma role admin já usada por `bin/migrate.php`.
 */
const TEST_DATABASE = 'autoschedule_test';

$app = new Config();
$config = $app->config('database');

$pdo = new \PDO(
    sprintf('%s:host=%s;port=%d;dbname=postgres', $config['driver'], $config['host'], $config['port']),
    $config['username'],
    $config['password'],
    [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION],
);

$statement = $pdo->prepare('SELECT 1 FROM pg_database WHERE datname = ?');
$statement->execute([TEST_DATABASE]);
$exists = (bool) $statement->fetchColumn();

if ($exists) {
    echo TEST_DATABASE . " already exists.\n";

    exit(0);
}

// Nome fixo, nunca input externo -- interpolar aqui não é injeção de SQL (CREATE DATABASE não aceita parâmetro bind).
$pdo->exec('CREATE DATABASE ' . TEST_DATABASE);
echo 'Created ' . TEST_DATABASE . ".\n";
