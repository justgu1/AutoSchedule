<?php

declare(strict_types=1);

namespace Tests\Domain\Auth;

use App\Domain\Auth\ClientType;
use App\Domain\Auth\GrantType;
use App\Domain\Auth\OAuthClient;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OAuthClientTest extends TestCase
{
    #[Test]
    public function create_monta_um_client_publico_sem_secret(): void
    {
        $client = OAuthClient::create(
            clientId: 'autoschedule-web',
            name: 'AutoSchedule Web',
            type: ClientType::Public,
            allowedGrantTypes: [GrantType::AuthorizationCode, GrantType::RefreshToken],
            redirectUris: ['urn:autoschedule:headless'],
            allowedScopes: ['profile:read'],
        );

        $this->assertNotSame('', $client->id);
        $this->assertNull($client->secretHash);
    }

    #[Test]
    public function create_rejeita_client_confidencial_sem_secret(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        OAuthClient::create(
            clientId: 'autoschedule-service',
            name: 'AutoSchedule Service',
            type: ClientType::Confidential,
            allowedGrantTypes: [GrantType::ClientCredentials],
            redirectUris: [],
            allowedScopes: [],
        );
    }

    #[Test]
    public function create_ignora_um_secret_informado_para_client_publico_sem_falhar(): void
    {
        $client = OAuthClient::create(
            clientId: 'autoschedule-web',
            name: 'AutoSchedule Web',
            type: ClientType::Public,
            allowedGrantTypes: [GrantType::AuthorizationCode],
            redirectUris: [],
            allowedScopes: [],
            plainSecret: 'should-be-ignored',
        );

        $this->assertNull($client->secretHash);
    }

    #[Test]
    public function verify_secret_confere_o_segredo_em_texto_puro_contra_o_hash(): void
    {
        $client = OAuthClient::create(
            clientId: 'autoschedule-service',
            name: 'AutoSchedule Service',
            type: ClientType::Confidential,
            allowedGrantTypes: [GrantType::ClientCredentials],
            redirectUris: [],
            allowedScopes: [],
            plainSecret: 'correct-secret',
        );

        $this->assertTrue($client->verifySecret('correct-secret'));
        $this->assertFalse($client->verifySecret('wrong-secret'));
    }

    #[Test]
    public function verify_secret_e_sempre_falso_para_client_publico(): void
    {
        $client = OAuthClient::create(
            clientId: 'autoschedule-web',
            name: 'AutoSchedule Web',
            type: ClientType::Public,
            allowedGrantTypes: [GrantType::AuthorizationCode],
            redirectUris: [],
            allowedScopes: [],
        );

        $this->assertFalse($client->verifySecret('anything'));
    }

    #[Test]
    public function supports_grant_type_reflete_a_lista_permitida(): void
    {
        $client = OAuthClient::create(
            clientId: 'autoschedule-web',
            name: 'AutoSchedule Web',
            type: ClientType::Public,
            allowedGrantTypes: [GrantType::AuthorizationCode],
            redirectUris: [],
            allowedScopes: [],
        );

        $this->assertTrue($client->supportsGrantType(GrantType::AuthorizationCode));
        $this->assertFalse($client->supportsGrantType(GrantType::ClientCredentials));
    }
}
