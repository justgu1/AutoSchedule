<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use App\Domain\Ports\DatabaseConnection;

final class PostgresConnection implements DatabaseConnection
{
    private ?\PDO $pdo = null;

    public function __construct(
        private readonly string $driver,
        private readonly string $host,
        private readonly int $port,
        private readonly string $database,
        private readonly string $username,
        private readonly string $password,
    ) {
    }

    public function pdo(): \PDO
    {
        return $this->pdo ??= new \PDO(
            sprintf('%s:host=%s;port=%d;dbname=%s', $this->driver, $this->host, $this->port, $this->database),
            $this->username,
            $this->password,
            [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ],
        );
    }
}
