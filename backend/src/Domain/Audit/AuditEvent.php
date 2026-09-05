<?php

declare(strict_types=1);

namespace App\Domain\Audit;

enum AuditEvent: string
{
    case LoginSucceeded = 'auth.login.succeeded';
    case LoginFailed = 'auth.login.failed';
    case RefreshTokenReused = 'auth.refresh_token.reused';
    case AccountDeleted = 'user.deleted';
}
