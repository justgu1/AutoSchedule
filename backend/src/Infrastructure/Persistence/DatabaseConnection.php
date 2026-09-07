<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

interface DatabaseConnection
{
    public function pdo(): \PDO;
}
