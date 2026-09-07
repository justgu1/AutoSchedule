<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Infrastructure\Persistence\DatabaseConnection;

/** Pra quando o teste precisa entregar um PDO específico (inclusive um propositalmente inútil). */
final readonly class FixedConnection implements DatabaseConnection
{
    public function __construct(private \PDO $pdo)
    {
    }

    public function pdo(): \PDO
    {
        return $this->pdo;
    }
}
