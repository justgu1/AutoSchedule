<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Application\Shared\ActorContext;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\Ports\AuditLogger;
use App\Domain\Auth\OAuthClient;
use App\Domain\Auth\Ports\OAuthClientRepository;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;

final readonly class CreateApiClient
{
    public function __construct(
        private OAuthClientRepository $clients,
        private AuditLogger $audit,
    ) {
    }

    /** @return array{0: OAuthClient, 1: string} client, secret em texto puro (só existe aqui, nunca mais recuperável) */
    public function __invoke(string $name, ActorContext $context): array
    {
        if ($context->actorId === null) {
            throw new DomainException('Authentication required.', DomainErrorType::Unauthorized);
        }

        [$rawSecret, $client] = OAuthClient::createForOwner($context->actorId, $name);

        $this->clients->insert($client);
        $this->audit->record($context->audits(AuditEvent::ApiClientCreated, $client->id));

        return [$client, $rawSecret];
    }
}
