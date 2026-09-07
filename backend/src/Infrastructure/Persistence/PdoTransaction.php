<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Ports\Transaction;

final readonly class PdoTransaction implements Transaction
{
    public function __construct(private DatabaseConnection $connection)
    {
    }

    /** Reentrante porque no HTTP já existe uma transação por request (a que carrega o contexto do RLS). */
    public function run(\Closure $work): mixed
    {
        $pdo = $this->connection->pdo();

        if ($pdo->inTransaction()) {
            return $work();
        }

        $pdo->beginTransaction();

        try {
            $result = $work();
            $pdo->commit();

            return $result;
        } catch (\Throwable $exception) {
            $pdo->rollBack();

            throw $exception;
        }
    }
}
