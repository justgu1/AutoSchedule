<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Auth\Jwt;

use App\Domain\Auth\AccessTokenClaims;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Domain\Users\UserRole;
use App\Infrastructure\Auth\Jwt\JwtTokenIssuer;
use Firebase\JWT\JWT;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Teste unitário: as chaves são efêmeras, geradas aqui na hora -- nunca o par
 * real de backend/storage/keys/ usado pela aplicação rodando.
 */
final class JwtTokenIssuerTest extends TestCase
{
    private string $privateKeyPem;
    private string $publicKeyPem;

    protected function setUp(): void
    {
        $resource = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($resource, $privateKeyPem);
        $this->privateKeyPem = $privateKeyPem;
        $this->publicKeyPem = openssl_pkey_get_details($resource)['key'];
    }

    #[Test]
    public function emite_e_decodifica_um_token_preservando_as_claims(): void
    {
        $issuer = $this->makeIssuer();
        $claims = AccessTokenClaims::issue('user-1', 'autoschedule-web', UserRole::Seller, ['profile:read', 'profile:write'], 900);

        $decoded = $issuer->decodeAccessToken($issuer->issueAccessToken($claims));

        $this->assertSame($claims->subject, $decoded->subject);
        $this->assertSame($claims->clientId, $decoded->clientId);
        $this->assertSame($claims->role, $decoded->role);
        $this->assertSame($claims->scopes, $decoded->scopes);
        $this->assertSame($claims->jti, $decoded->jti);
        $this->assertSame($claims->expiresAt->getTimestamp(), $decoded->expiresAt->getTimestamp());
    }

    #[Test]
    public function m2m_sem_role_decodifica_com_role_null(): void
    {
        $issuer = $this->makeIssuer();
        $claims = AccessTokenClaims::issue('autoschedule-service', 'autoschedule-service', null, ['service:internal'], 900);

        $decoded = $issuer->decodeAccessToken($issuer->issueAccessToken($claims));

        $this->assertNull($decoded->role);
    }

    #[Test]
    public function rejeita_token_expirado(): void
    {
        $issuer = $this->makeIssuer();
        $claims = AccessTokenClaims::issue('user-1', 'autoschedule-web', UserRole::Customer, [], -1);
        $token = $issuer->issueAccessToken($claims);

        $this->expectException(DomainException::class);
        $issuer->decodeAccessToken($token);
    }

    #[Test]
    public function rejeita_assinatura_de_uma_chave_diferente(): void
    {
        $issuer = $this->makeIssuer();
        $otherKeyResource = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($otherKeyResource, $otherPrivateKeyPem);

        $claims = AccessTokenClaims::issue('user-1', 'autoschedule-web', UserRole::Customer, [], 900);
        $tokenSignedByAnotherKey = JWT::encode([
            'iss' => 'autoschedule',
            'aud' => 'autoschedule-api',
            'sub' => $claims->subject,
            'client_id' => $claims->clientId,
            'scope' => '',
            'jti' => $claims->jti,
            'iat' => time(),
            'exp' => $claims->expiresAt->getTimestamp(),
        ], $otherPrivateKeyPem, 'RS256');

        $this->expectException(DomainException::class);
        $issuer->decodeAccessToken($tokenSignedByAnotherKey);
    }

    #[Test]
    public function rejeita_algoritmo_diferente_de_rs256_mesmo_assinado_com_a_chave_publica(): void
    {
        $issuer = $this->makeIssuer();

        // Tentativa clássica de confusão de algoritmo: assina HS256 usando a
        // chave pública RSA (que é, bem, pública) como se fosse segredo HMAC.
        $forgedToken = JWT::encode([
            'iss' => 'autoschedule',
            'aud' => 'autoschedule-api',
            'sub' => 'attacker',
            'client_id' => 'autoschedule-web',
            'scope' => '',
            'jti' => 'forged',
            'iat' => time(),
            'exp' => time() + 900,
        ], $this->publicKeyPem, 'HS256');

        $this->expectException(DomainException::class);
        $issuer->decodeAccessToken($forgedToken);
    }

    #[Test]
    public function rejeita_issuer_ou_audience_inesperados(): void
    {
        $wrongAudienceIssuer = new JwtTokenIssuer($this->privateKeyPem, $this->publicKeyPem, 'autoschedule', 'some-other-api');
        $claims = AccessTokenClaims::issue('user-1', 'autoschedule-web', UserRole::Customer, [], 900);
        $token = $wrongAudienceIssuer->issueAccessToken($claims);

        $issuer = $this->makeIssuer(); // expects audience "autoschedule-api"

        $this->expectException(DomainException::class);
        $issuer->decodeAccessToken($token);
    }

    #[Test]
    public function decode_lanca_domain_exception_unauthorized(): void
    {
        $issuer = $this->makeIssuer();

        try {
            $issuer->decodeAccessToken('not-a-jwt-at-all');
            $this->fail('Expected a DomainException to be thrown.');
        } catch (DomainException $exception) {
            $this->assertSame(DomainErrorType::Unauthorized, $exception->type());
        }
    }

    private function makeIssuer(): JwtTokenIssuer
    {
        return new JwtTokenIssuer($this->privateKeyPem, $this->publicKeyPem, 'autoschedule', 'autoschedule-api');
    }
}
