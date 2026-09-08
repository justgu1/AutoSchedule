<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Domain\Auth\GrantType;
use App\Domain\Auth\OAuthClient;
use App\Domain\Auth\Ports\OAuthClientRepository;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;

/** Porta de entrada comum dos quatro grants. */
final readonly class ClientAuthenticator
{
    public function __construct(private OAuthClientRepository $clients)
    {
    }

    public function authenticate(string $clientId, GrantType $grantType): OAuthClient
    {
        $client = $this->clients->findByClientId($clientId);

        // Mesma resposta pros dois casos: não vaza quais client_id existem nem o que cada um pode.
        if (!$client instanceof OAuthClient || !$client->supportsGrantType($grantType)) {
            throw new DomainException('Invalid client.', DomainErrorType::Unauthorized);
        }

        return $client;
    }
}
