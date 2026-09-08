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
    case VehicleCreated = 'vehicle.created';
    case VehicleUpdated = 'vehicle.updated';
    case VehicleDealershipReassigned = 'vehicle.dealership_reassigned';
    case VehicleImagesAdded = 'vehicle.images_added';
    case VehicleImageRemoved = 'vehicle.image_removed';
    case VehicleImagesReordered = 'vehicle.images_reordered';
    case VehicleTrashed = 'vehicle.trashed';
    case VehicleRestored = 'vehicle.restored';
    case VehiclePurged = 'vehicle.purged';
    case AvailabilityCreated = 'availability.created';
    case AvailabilityUpdated = 'availability.updated';
    case AvailabilityDeleted = 'availability.deleted';
    case AppointmentCreated = 'appointment.created';
    case AppointmentConfirmed = 'appointment.confirmed';
    case AppointmentCancelled = 'appointment.cancelled';
    case AppointmentCompleted = 'appointment.completed';

    /** O afetado sai do próprio evento. Sem `default`: domínio novo tem que aparecer aqui, não virar 'User' calado. */
    public function auditableType(): AuditableType
    {
        return match (explode('.', $this->value)[0]) {
            'auth', 'user' => AuditableType::User,
            'dealership' => AuditableType::Dealership,
            'vehicle' => AuditableType::Vehicle,
            'availability' => AuditableType::Availability,
            'appointment' => AuditableType::Appointment,
            default => throw new \LogicException(sprintf('Event "%s" has no auditable type.', $this->value)),
        };
    }
}
