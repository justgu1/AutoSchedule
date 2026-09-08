<?php

declare(strict_types=1);

namespace App\Application\Dealership;

use App\Application\Dealership\DTO\DealershipProfile;
use App\Application\Shared\ActorContext;
use App\Domain\Dealership\Dealership;
use App\Domain\Dealership\Ports\DealershipRepository;

/** Admin vê todas; seller só as próprias. */
final readonly class ListDealerships
{
    public function __construct(
        private DealershipRepository $dealerships,
        private DealershipPhotos $photos,
    ) {
    }

    /** @return array{items: list<DealershipProfile>, total: int} */
    public function __invoke(ActorContext $context, int $limit, int $offset): array
    {
        if ($context->isAdmin()) {
            return [
                'items' => $this->toProfiles($this->dealerships->findPage($limit, $offset)),
                'total' => $this->dealerships->count(),
            ];
        }

        $ownerId = (string) $context->actorId;

        return [
            'items' => $this->toProfiles($this->dealerships->findByOwner($ownerId, $limit, $offset)),
            'total' => $this->dealerships->countByOwner($ownerId),
        ];
    }

    /**
     * @param list<Dealership> $dealerships
     * @return list<DealershipProfile>
     */
    private function toProfiles(array $dealerships): array
    {
        return array_map(
            fn (Dealership $dealership): DealershipProfile => DealershipProfile::fromDealership($dealership, $this->photos->urlFor($dealership)),
            $dealerships,
        );
    }
}
