<?php

declare(strict_types=1);

namespace App\Application\Dealership;

use App\Application\Dealership\DTO\DealershipProfile;
use App\Application\Shared\ActorContext;
use App\Application\Shared\ValidatedInput;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\Ports\AuditLogger;
use App\Domain\Dealership\Dealership;
use App\Domain\Dealership\Ports\DealershipRepository;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;

/** Seller sempre vira dono do que cria; só admin escolhe o dono (`owner_user_id`). */
final readonly class CreateDealership
{
    public function __construct(
        private DealershipRepository $dealerships,
        private DealershipPhotos $photos,
        private AuditLogger $audit,
    ) {
    }

    public function __invoke(ValidatedInput $data, ActorContext $context): DealershipProfile
    {
        $ownerUserId = $context->isAdmin() ? $data->stringOrNull('owner_user_id') : $context->actorId;

        if ($ownerUserId === null) {
            throw new DomainException('Invalid data.', DomainErrorType::Validation, ['owner_user_id' => 'An owner is required.']);
        }

        $dealership = Dealership::register(
            ownerUserId: $ownerUserId,
            name: $data->string('name'),
            zipCode: $data->string('zip_code'),
            address: $data->string('address'),
            number: $data->string('number'),
            complement: $data->stringOrNull('complement'),
            neighborhood: $data->string('neighborhood'),
            city: $data->string('city'),
            state: $data->string('state'),
            phone: $data->stringOrNull('phone'),
            email: $data->stringOrNull('email'),
        );

        $this->dealerships->insert($dealership);
        $this->audit->record(AuditEvent::DealershipCreated, $context->actorId, 'Dealership', $dealership->id, [], $context->ipAddress, $context->userAgent);

        return DealershipProfile::fromDealership($dealership, $this->photos->urlFor($dealership));
    }
}
