<?php

declare(strict_types=1);

namespace App\Domain\Users;

enum UserStatus: string
{
    case Active = 'active';
    case Trashed = 'trashed';
    case Deleted = 'deleted';
}
