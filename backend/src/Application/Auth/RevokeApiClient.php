<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Application\Shared\ActorContext;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\Ports\AuditLogger;
use App\Domain\Auth\Ports\OAuthClientRepository;

final readonly class RevokeApiClient
{
    public function __construct(
        private ApiClientFinder $finder,
        private OAuthClientRepository $clients,
        private AuditLogger $audit,
    ) {
    }

    public function __invoke(?string $id, ActorContext $context): void
    {
        $client = $this->finder->findOrFail($id, $context);

        $this->clients->update($client->revoked());
        $this->audit->record($context->audits(AuditEvent::ApiClientRevoked, $client->id));
    }
}
