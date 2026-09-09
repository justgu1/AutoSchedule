<?php

declare(strict_types=1);

namespace Tests\Application\Auth;

use App\Application\Auth\ApiClientFinder;
use App\Application\Auth\RevokeApiClient;
use App\Application\Shared\ActorContext;
use App\Domain\Audit\AuditEvent;
use App\Domain\Auth\OAuthClient;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Domain\User\UserRole;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeAuditLogger;
use Tests\Support\InMemoryOAuthClientRepository;

final class RevokeApiClientTest extends TestCase
{
    #[Test]
    public function revoga_o_client_do_proprio_dono(): void
    {
        [, $client] = OAuthClient::createForOwner('user-1', 'Minha integração');
        $clients = new InMemoryOAuthClientRepository($client);
        $audit = new FakeAuditLogger();
        $revoke = new RevokeApiClient(new ApiClientFinder($clients), $clients, $audit);

        $revoke($client->id, new ActorContext('user-1', UserRole::Seller));

        $stored = $clients->findByIdForOwner($client->id, 'user-1');
        $this->assertNotNull($stored);
        $this->assertTrue($stored->isRevoked());
        $this->assertSame([AuditEvent::ApiClientRevoked], $audit->events);
    }

    #[Test]
    public function dono_errado_recebe_404_e_nao_revoga_nada(): void
    {
        [, $client] = OAuthClient::createForOwner('user-1', 'Minha integração');
        $clients = new InMemoryOAuthClientRepository($client);
        $revoke = new RevokeApiClient(new ApiClientFinder($clients), $clients, new FakeAuditLogger());

        try {
            $revoke($client->id, new ActorContext('user-2', UserRole::Seller));
            $this->fail('Expected a DomainException to be thrown.');
        } catch (DomainException $exception) {
            $this->assertSame(DomainErrorType::NotFound, $exception->type());
        }

        $this->assertFalse($clients->findByIdForOwner($client->id, 'user-1')?->isRevoked());
    }
}
