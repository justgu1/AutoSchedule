<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    private const array DATABASE_ENV_KEYS = [
        'DB_DRIVER',
        'DB_HOST',
        'DB_PORT',
        'DB_DATABASE',
        'DB_USERNAME',
        'DB_PASSWORD',
    ];

    /** @var array<string, string|false> */
    private array $envBackup = [];

    protected function setUp(): void
    {
        foreach (self::DATABASE_ENV_KEYS as $key) {
            $this->envBackup[$key] = getenv($key);
            putenv($key);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->envBackup as $key => $value) {
            putenv($value === false ? $key : "{$key}={$value}");
        }
    }

    #[Test]
    public function database_usa_valores_padrao_quando_env_nao_definido(): void
    {
        $config = require dirname(__DIR__) . '/config/app.php';

        $this->assertSame([
            'driver' => 'pgsql',
            'host' => '127.0.0.1',
            'port' => 5432,
            'database' => 'autoschedule',
            'username' => 'pgsql',
            'password' => 'password',
        ], $config['database']);
    }

    #[Test]
    public function database_le_os_valores_das_variaveis_de_ambiente(): void
    {
        putenv('DB_DRIVER=mysql');
        putenv('DB_HOST=db.internal');
        putenv('DB_PORT=6543');
        putenv('DB_DATABASE=custom');
        putenv('DB_USERNAME=admin');
        putenv('DB_PASSWORD=secret');

        $config = require dirname(__DIR__) . '/config/app.php';

        $this->assertSame([
            'driver' => 'mysql',
            'host' => 'db.internal',
            'port' => 6543,
            'database' => 'custom',
            'username' => 'admin',
            'password' => 'secret',
        ], $config['database']);
    }
}
