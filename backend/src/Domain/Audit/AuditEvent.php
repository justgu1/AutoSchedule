<?php

declare(strict_types=1);

namespace App\Domain\Audit;

enum AuditEvent: string
{
    case LoginSucceeded = 'auth.login.succeeded';
    case LoginFailed = 'auth.login.failed';
    case RefreshTokenReused = 'auth.refresh_token.reused';
    case ServiceTokenIssued = 'auth.service_token.issued';
    case UserCreated = 'user.created';
    case ProfileUpdated = 'user.profile_updated';
    case PasswordChanged = 'user.password_changed';
    case AccountDeleted = 'user.deleted';
    case AccountTrashed = 'user.trashed';
    case AccountRestored = 'user.restored';
    case AccountPurged = 'user.purged';
}
