<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Domain\Auth\GrantType;
use App\Domain\Auth\OAuthClient;
use App\Domain\Auth\Ports\OAuthClientRepository;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;

/** Porta de entrada comum dos quatro fluxos de `POST /oauth/token`. */
final readonly class ClientAuthenticator
{
    public function __construct(private OAuthClientRepository $clients)
    {
    }

    public function authenticate(string $clientId, GrantType $grantType): OAuthClient
    {
        $client = $this->clients->findByClientId($clientId);

        // Cliente inexistente e cliente sem esse grant dão a mesma resposta --
        // não vaza quais client_id existem nem o que cada um pode fazer.
        if (!$client instanceof OAuthClient || !$client->supportsGrantType($grantType)) {
            throw new DomainException('Invalid client.', DomainErrorType::Unauthorized);
        }

        return $client;
    }
}
