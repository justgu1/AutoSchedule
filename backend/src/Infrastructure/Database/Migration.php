<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

interface Migration
{
    public function up(\PDO $pdo): void;

    public function down(\PDO $pdo): void;
}
