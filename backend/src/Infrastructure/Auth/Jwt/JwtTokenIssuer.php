<?php

declare(strict_types=1);

namespace App\Infrastructure\Auth\Jwt;

use App\Domain\Auth\Ports\TokenIssuer;
use App\Domain\Auth\ValueObjects\AccessTokenClaims;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Domain\Users\UserRole;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

final class JwtTokenIssuer implements TokenIssuer
{
    public function __construct(
        private readonly string $privateKeyPem,
        private readonly string $publicKeyPem,
        private readonly string $issuer,
        private readonly string $audience,
    ) {
    }

    public function issueAccessToken(AccessTokenClaims $claims): string
    {
        $payload = [
            'iss' => $this->issuer,
            'aud' => $this->audience,
            'sub' => $claims->subject,
            'client_id' => $claims->clientId,
            'scope' => implode(' ', $claims->scopes),
            'jti' => $claims->jti,
            'iat' => (new \DateTimeImmutable())->getTimestamp(),
            'exp' => $claims->expiresAt->getTimestamp(),
        ];

        if ($claims->role !== null) {
            $payload['role'] = $claims->role->value;
        }

        return JWT::encode($payload, $this->privateKeyPem, 'RS256');
    }

    public function decodeAccessToken(string $token): AccessTokenClaims
    {
        try {
            // Passar um Key fixa o algoritmo em RS256 -- o firebase/php-jwt recusa
            // verificar um token cujo header diga outro alg, o que barra o ataque
            // clássico de "trocar RS256 por HS256 usando a chave pública como
            // segredo HMAC".
            $decoded = JWT::decode($token, new Key($this->publicKeyPem, 'RS256'));
        } catch (\Throwable) {
            throw new DomainException('Invalid or expired access token.', DomainErrorType::Unauthorized);
        }

        if (
            ($decoded->iss ?? null) !== $this->issuer
            || ($decoded->aud ?? null) !== $this->audience
            || !isset($decoded->sub, $decoded->client_id, $decoded->jti, $decoded->exp)
        ) {
            throw new DomainException('Invalid or expired access token.', DomainErrorType::Unauthorized);
        }

        return new AccessTokenClaims(
            subject: $decoded->sub,
            clientId: $decoded->client_id,
            role: isset($decoded->role) ? UserRole::from($decoded->role) : null,
            scopes: isset($decoded->scope) && $decoded->scope !== '' ? explode(' ', $decoded->scope) : [],
            jti: $decoded->jti,
            expiresAt: (new \DateTimeImmutable())->setTimestamp((int) $decoded->exp),
        );
    }
}
