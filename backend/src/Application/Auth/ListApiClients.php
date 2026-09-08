<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Application\Shared\ActorContext;
use App\Domain\Auth\OAuthClient;
use App\Domain\Auth\Ports\OAuthClientRepository;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;

final readonly class ListApiClients
{
    public function __construct(private OAuthClientRepository $clients)
    {
    }

    /** @return list<OAuthClient> */
    public function __invoke(ActorContext $context): array
    {
        if ($context->actorId === null) {
            throw new DomainException('Authentication required.', DomainErrorType::Unauthorized);
        }

        return $this->clients->findAllForOwner($context->actorId);
    }
}
