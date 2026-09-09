<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Application\Shared\ActorContext;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\Ports\AuditLogger;
use App\Domain\Auth\OAuthClient;
use App\Domain\Auth\Ports\OAuthClientRepository;

final readonly class RotateApiClientSecret
{
    public function __construct(
        private ApiClientFinder $finder,
        private OAuthClientRepository $clients,
        private AuditLogger $audit,
    ) {
    }

    /** @return array{0: OAuthClient, 1: string} client, novo secret em texto puro */
    public function __invoke(?string $id, ActorContext $context): array
    {
        $client = $this->finder->findOrFail($id, $context);
        [$rawSecret, $rotated] = $client->rotateSecret();

        $this->clients->update($rotated);
        $this->audit->record($context->audits(AuditEvent::ApiClientSecretRotated, $rotated->id));

        return [$rotated, $rawSecret];
    }
}
