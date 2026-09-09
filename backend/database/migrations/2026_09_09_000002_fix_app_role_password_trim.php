<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Schema\Migration;

/**
 * `2026_09_09_000001` setou sem `trim()` -- divergiu da senha que a conexão usa de verdade.
 * Precisa de migration nova: já rodou, `MigrationRunner` não repete por nome.
 */
return new class () implements Migration {
    public function up(\PDO $pdo): void
    {
        $password = trim(getenv('DB_APP_PASSWORD') ?: '') ?: 'changeme';
        $pdo->exec('ALTER ROLE autoschedule_app WITH PASSWORD ' . $pdo->quote($password));
    }

    public function down(\PDO $pdo): void
    {
        $password = trim(getenv('DB_APP_PASSWORD') ?: '') ?: 'changeme';
        $pdo->exec('ALTER ROLE autoschedule_app WITH PASSWORD ' . $pdo->quote($password));
    }
};
