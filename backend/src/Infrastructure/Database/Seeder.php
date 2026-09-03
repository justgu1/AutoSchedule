<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

interface Seeder
{
    public function run(\PDO $pdo): void;
}
