<?php

declare(strict_types=1);

namespace App\Domain\User;

enum UserRole: string
{
    case Admin = 'admin';
    case Seller = 'seller';
    case Customer = 'customer';
}
