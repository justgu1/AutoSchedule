<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Infrastructure\Database\PostgresConnection;

final class TestDatabase
{
    /** Env com fallback porque o compose e o CI expõem o Postgres em endereços diferentes. */
    public static function connect(): PostgresConnection
    {
        return self::open(getenv('DB_USERNAME') ?: 'pgsql', getenv('DB_PASSWORD') ?: 'password');
    }

    /** A role restrita é a única que o RLS de fato alcança; a admin é superuser e ignora policy. */
    public static function connectAsApp(): PostgresConnection
    {
        return self::open(getenv('DB_APP_USERNAME') ?: 'autoschedule_app', getenv('DB_APP_PASSWORD') ?: 'changeme');
    }

    private static function open(string $username, string $password): PostgresConnection
    {
        return new PostgresConnection(
            driver: getenv('DB_DRIVER') ?: 'pgsql',
            host: getenv('DB_HOST') ?: '127.0.0.1',
            port: (int) (getenv('DB_PORT') ?: 5432),
            database: getenv('DB_DATABASE') ?: 'autoschedule',
            username: $username,
            password: $password,
        );
    }
}
