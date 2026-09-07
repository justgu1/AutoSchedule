<?php

declare(strict_types=1);

namespace App\Domain\Audit;

/** Tipo da entidade afetada. Cresce por caso novo aqui, não por migration. */
enum AuditableType: string
{
    case User = 'User';
    case Dealership = 'Dealership';
}
