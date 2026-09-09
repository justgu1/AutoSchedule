<?php

declare(strict_types=1);

namespace Tests\Application\Auth;

use App\Application\Auth\ApiClientFinder;
use App\Application\Auth\RotateApiClientSecret;
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

final class RotateApiClientSecretTest extends TestCase
{
    #[Test]
    public function rotaciona_o_secret_mantendo_o_client_id(): void
    {
        [$originalSecret, $client] = OAuthClient::createForOwner('user-1', 'Minha integração');
        $clients = new InMemoryOAuthClientRepository($client);
        $audit = new FakeAuditLogger();
        $rotate = new RotateApiClientSecret(new ApiClientFinder($clients), $clients, $audit);

        [$rotated, $newSecret] = $rotate($client->id, new ActorContext('user-1', UserRole::Seller));

        $stored = $clients->findByIdForOwner($client->id, 'user-1');

        $this->assertSame($client->clientId, $rotated->clientId);
        $this->assertNotSame($originalSecret, $newSecret);
        $this->assertNotNull($stored);
        $this->assertTrue($stored->verifySecret($newSecret));
        $this->assertFalse($stored->verifySecret($originalSecret));
        $this->assertSame([AuditEvent::ApiClientSecretRotated], $audit->events);
    }

    #[Test]
    public function dono_errado_recebe_404_nao_403(): void
    {
        [, $client] = OAuthClient::createForOwner('user-1', 'Minha integração');
        $clients = new InMemoryOAuthClientRepository($client);
        $rotate = new RotateApiClientSecret(new ApiClientFinder($clients), $clients, new FakeAuditLogger());

        try {
            $rotate($client->id, new ActorContext('user-2', UserRole::Seller));
            $this->fail('Expected a DomainException to be thrown.');
        } catch (DomainException $exception) {
            $this->assertSame(DomainErrorType::NotFound, $exception->type());
        }
    }
}
