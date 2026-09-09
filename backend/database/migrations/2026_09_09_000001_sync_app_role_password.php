<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Schema\Migration;

/**
 * `2026_09_04_000004` só cria o role com senha na 1ª vez -- resselar o secret depois nunca
 * reflete no Postgres (já causou incidente real, role preso na senha antiga).
 */
return new class () implements Migration {
    public function up(\PDO $pdo): void
    {
        $password = getenv('DB_APP_PASSWORD') ?: 'changeme';
        $pdo->exec('ALTER ROLE autoschedule_app WITH PASSWORD ' . $pdo->quote($password));
    }

    public function down(\PDO $pdo): void
    {
        $password = getenv('DB_APP_PASSWORD') ?: 'changeme';
        $pdo->exec('ALTER ROLE autoschedule_app WITH PASSWORD ' . $pdo->quote($password));
    }
};
