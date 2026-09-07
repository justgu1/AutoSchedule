<?php

declare(strict_types=1);

namespace App\Application\Dealership;

use App\Application\Dealership\DTO\DealershipProfile;
use App\Application\Shared\ActorContext;
use App\Application\Shared\ValidatedInput;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\Ports\AuditLogger;
use App\Domain\Dealership\Ports\DealershipRepository;

/**
 * `owner_user_id` só chega aqui quando quem chama é admin (a rota é a mesma
 * que reassocia dono, sem endpoint paralelo pra isso) -- reassociação vira um
 * evento de auditoria próprio, além do update comum.
 */
final readonly class UpdateDealership
{
    public function __construct(
        private DealershipFinder $finder,
        private DealershipRepository $dealerships,
        private DealershipPhotos $photos,
        private AuditLogger $audit,
    ) {
    }

    public function __invoke(?string $identifier, ValidatedInput $changes, ActorContext $context): DealershipProfile
    {
        $dealership = $this->finder->findOrFail($identifier);
        $previousOwnerUserId = $dealership->ownerUserId;

        $updated = $dealership->withProfile(
            name: $changes->stringOr('name', $dealership->name),
            zipCode: $changes->stringOr('zip_code', $dealership->zipCode),
            address: $changes->stringOr('address', $dealership->address),
            number: $changes->stringOr('number', $dealership->number),
            complement: $changes->stringOrNull('complement') ?? $dealership->complement,
            neighborhood: $changes->stringOr('neighborhood', $dealership->neighborhood),
            city: $changes->stringOr('city', $dealership->city),
            state: $changes->stringOr('state', $dealership->state),
            phone: $changes->stringOrNull('phone') ?? $dealership->phone,
            email: $changes->stringOrNull('email') ?? $dealership->email,
            latitude: $dealership->latitude,
            longitude: $dealership->longitude,
            googlePlaceId: $dealership->googlePlaceId,
        );

        if ($changes->has('owner_user_id') && $changes->string('owner_user_id') !== $previousOwnerUserId) {
            $updated = $updated->withOwner($changes->string('owner_user_id'));
        }

        $this->dealerships->update($updated);
        $this->audit->record(AuditEvent::DealershipUpdated, $context->actorId, 'Dealership', $updated->id, ['fields' => $changes->fields()], $context->ipAddress, $context->userAgent);

        if ($updated->ownerUserId !== $previousOwnerUserId) {
            $this->audit->record(
                AuditEvent::DealershipOwnerReassigned,
                $context->actorId,
                'Dealership',
                $updated->id,
                ['from' => $previousOwnerUserId, 'to' => $updated->ownerUserId],
                $context->ipAddress,
                $context->userAgent,
            );
        }

        return DealershipProfile::fromDealership($updated, $this->photos->urlFor($updated));
    }
}
