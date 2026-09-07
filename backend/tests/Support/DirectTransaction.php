<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Application\Ports\Transaction;

/** Para suíte sem banco: executa o trabalho, sem transação nenhuma para abrir. */
final readonly class DirectTransaction implements Transaction
{
    public function run(\Closure $work): mixed
    {
        return $work();
    }
}
