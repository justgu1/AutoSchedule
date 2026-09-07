<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

interface DatabaseConnection
{
    public function pdo(): \PDO;
}
