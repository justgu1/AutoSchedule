<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Application\Auth\DTO\TokenPair;
use App\Application\Shared\ActorContext;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\Ports\AuditLogger;
use App\Domain\Auth\ClientType;
use App\Domain\Auth\GrantType;
use App\Domain\Auth\Ports\TokenIssuer;
use App\Domain\Auth\ValueObjects\AccessTokenClaims;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;

/** M2M não tem sessão pra renovar, então sai access token sem refresh token. */
final readonly class IssueServiceToken
{
    public function __construct(
        private ClientAuthenticator $clients,
        private TokenIssuer $tokens,
        private AuditLogger $audit,
        private int $accessTokenTtl,
    ) {
    }

    public function __invoke(string $clientId, string $clientSecret, ActorContext $context): TokenPair
    {
        $client = $this->clients->authenticate($clientId, GrantType::ClientCredentials);

        if ($client->type !== ClientType::Confidential || !$client->verifySecret($clientSecret)) {
            throw new DomainException('Invalid client credentials.', DomainErrorType::Unauthorized);
        }

        $accessToken = $this->tokens->issueAccessToken(AccessTokenClaims::issue(
            subject: $client->clientId,
            clientId: $client->clientId,
            role: null,
            scopes: $client->allowedScopes,
            ttlSeconds: $this->accessTokenTtl,
        ));

        // Sem actor nem alvo: quem se autenticou é o client, e ele vai no context.
        $this->audit->record(AuditEvent::ServiceTokenIssued, null, 'User', null, ['client_id' => $client->clientId], $context->ipAddress, $context->userAgent);

        return new TokenPair($accessToken, $this->accessTokenTtl, $client->allowedScopes);
    }
}
