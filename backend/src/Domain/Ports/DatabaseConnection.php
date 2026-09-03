<?php

declare(strict_types=1);

namespace App\Domain\Ports;

interface DatabaseConnection
{
    public function pdo(): \PDO;
}
