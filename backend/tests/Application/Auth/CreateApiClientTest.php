<?php

declare(strict_types=1);

namespace Tests\Application\Auth;

use App\Application\Auth\CreateApiClient;
use App\Application\Shared\ActorContext;
use App\Domain\Audit\AuditEvent;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Domain\User\UserRole;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeAuditLogger;
use Tests\Support\InMemoryOAuthClientRepository;

final class CreateApiClientTest extends TestCase
{
    #[Test]
    public function cria_um_client_m2m_pro_ator_autenticado_e_devolve_o_secret_uma_vez(): void
    {
        $clients = new InMemoryOAuthClientRepository();
        $audit = new FakeAuditLogger();
        $createApiClient = new CreateApiClient($clients, $audit);

        [$client, $secret] = $createApiClient('Minha integração', new ActorContext('user-1', UserRole::Seller));

        $this->assertNotSame('', $secret);
        $this->assertSame('user-1', $client->ownerUserId);
        $this->assertTrue($client->verifySecret($secret));
        $this->assertSame([AuditEvent::ApiClientCreated], $audit->events);
        $this->assertSame($client->id, $audit->entries[0]->auditableId);
        $this->assertNotNull($clients->findByIdForOwner($client->id, 'user-1'));
    }

    #[Test]
    public function exige_autenticacao(): void
    {
        $createApiClient = new CreateApiClient(new InMemoryOAuthClientRepository(), new FakeAuditLogger());

        try {
            $createApiClient('Minha integração', new ActorContext());
            $this->fail('Expected a DomainException to be thrown.');
        } catch (DomainException $exception) {
            $this->assertSame(DomainErrorType::Unauthorized, $exception->type());
        }
    }
}
