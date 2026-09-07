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
    case DealershipCreated = 'dealership.created';
    case DealershipUpdated = 'dealership.updated';
    case DealershipTrashed = 'dealership.trashed';
    case DealershipRestored = 'dealership.restored';
    case DealershipPurged = 'dealership.purged';
    case DealershipOwnerReassigned = 'dealership.owner_reassigned';
    case DealershipPhotoUpdated = 'dealership.photo_updated';
    case DealershipPhotoRemoved = 'dealership.photo_removed';

    /** O afetado sai do próprio evento. Sem `default`: domínio novo tem que aparecer aqui, não virar 'User' calado. */
    public function auditableType(): AuditableType
    {
        return match (explode('.', $this->value)[0]) {
            'auth', 'user' => AuditableType::User,
            'dealership' => AuditableType::Dealership,
            default => throw new \LogicException(sprintf('Event "%s" has no auditable type.', $this->value)),
        };
    }
}
