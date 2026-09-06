<?php

declare(strict_types=1);

namespace App\Domain\Shared;

/** Ciclo de vida LGPD compartilhado por qualquer entidade com lixeira reversível (User, Dealership, ...). */
enum TrashableStatus: string
{
    case Active = 'active';
    case Trashed = 'trashed';
    case Deleted = 'deleted';
}
