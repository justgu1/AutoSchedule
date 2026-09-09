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
use App\Domain\User\Ports\UserRepository;
use App\Domain\User\User;

/** M2M não tem sessão pra renovar, então sai access token sem refresh token. */
final readonly class IssueServiceToken
{
    public function __construct(
        private ClientAuthenticator $clients,
        private UserRepository $users,
        private TokenIssuer $tokens,
        private AuditLogger $audit,
        private TokenTtl $ttl,
    ) {
    }

    public function __invoke(string $clientId, string $clientSecret, ActorContext $context): TokenPair
    {
        $client = $this->clients->authenticate($clientId, GrantType::ClientCredentials);

        if ($client->type !== ClientType::Confidential || !$client->verifySecret($clientSecret)) {
            throw new DomainException('Invalid client credentials.', DomainErrorType::Unauthorized);
        }

        $subject = $client->clientId;
        $role = null;
        $auditContext = $context;

        if ($client->ownerUserId !== null) {
            $owner = $this->users->findById($client->ownerUserId);

            // Dono apagado/trashed: client fica sem identidade válida pra emprestar -- nega, não regride pra "sem dono".
            if (!$owner instanceof User || !$owner->trash->isActive()) {
                throw new DomainException('Invalid client credentials.', DomainErrorType::Unauthorized);
            }

            $subject = $owner->id;
            $role = $owner->role;
            $auditContext = $context->actedBy($owner->id);
        }

        $accessToken = $this->tokens->issueAccessToken(AccessTokenClaims::issue(
            subject: $subject,
            clientId: $client->clientId,
            role: $role,
            scopes: $client->allowedScopes,
            ttlSeconds: $this->ttl->accessSeconds,
        ));

        $this->audit->record($auditContext->audits(AuditEvent::ServiceTokenIssued, context: ['client_id' => $client->clientId]));

        return new TokenPair($accessToken, $this->ttl->accessSeconds, $client->allowedScopes);
    }
}
