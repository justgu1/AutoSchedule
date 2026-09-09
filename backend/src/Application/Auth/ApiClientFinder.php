<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Application\Shared\ActorContext;
use App\Domain\Auth\OAuthClient;
use App\Domain\Auth\Ports\OAuthClientRepository;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;

/** Dono errado nem sabe que o id existe -- mesma resposta de "não existe", como `ClientAuthenticator`. */
final readonly class ApiClientFinder
{
    public function __construct(private OAuthClientRepository $clients)
    {
    }

    public function findOrFail(?string $id, ActorContext $context): OAuthClient
    {
        $client = $id === null || $context->actorId === null ? null : $this->clients->findByIdForOwner($id, $context->actorId);

        return $client ?? throw new DomainException('Resource not found.', DomainErrorType::NotFound);
    }
}
