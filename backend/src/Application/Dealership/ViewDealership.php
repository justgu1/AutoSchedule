<?php

declare(strict_types=1);

namespace App\Application\Dealership;

use App\Application\Dealership\DTO\DealershipProfile;
use App\Application\Dealership\DTO\PublicDealershipProfile;
use App\Application\Shared\ActorContext;
use App\Domain\User\Ports\UserRepository;
use App\Domain\User\User;

/**
 * Uma leitura, dois perfis: dono e admin recebem o completo, todo o resto recebe o público.
 * O RLS reforça isso na própria query, então nem depende deste ramo estar certo.
 */
final readonly class ViewDealership
{
    public function __construct(
        private DealershipFinder $finder,
        private DealershipPhotos $photos,
        private UserRepository $users,
    ) {
    }

    public function __invoke(?string $identifier, ActorContext $context): DealershipProfile|PublicDealershipProfile
    {
        $dealership = $this->finder->findOrFail($identifier);

        if ($context->isAdmin() || ($context->actorId !== null && $context->actorId === $dealership->ownerUserId)) {
            return DealershipProfile::fromDealership($dealership, $this->photos->urlFor($dealership));
        }

        $seller = $this->users->findById($dealership->ownerUserId);

        return PublicDealershipProfile::fromDealership(
            $dealership,
            $this->photos->urlFor($dealership),
            $seller instanceof User ? $seller->name : null,
        );
    }
}
