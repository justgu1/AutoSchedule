<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

enum DomainErrorType
{
    case NotFound;
    case Validation;
    case Conflict;
}
