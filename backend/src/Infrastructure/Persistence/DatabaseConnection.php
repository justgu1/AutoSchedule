<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

interface DatabaseConnection
{
    public function pdo(): \PDO;

    /** @param array<string, string|int|bool|null> $params */
    public function execute(string $sql, array $params = []): \PDOStatement;
}
