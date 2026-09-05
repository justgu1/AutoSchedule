<?php

declare(strict_types=1);

namespace App\Infrastructure\Auth\Google;

use App\Domain\Auth\Ports\GoogleIdTokenVerifier;
use App\Domain\Auth\ValueObjects\GoogleIdentityClaims;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Infrastructure\Redis\RedisConnection;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;

/**
 * Verifica o id_token do Google Identity Services -- sem SDK do Google, só o
 * firebase/php-jwt que já é dependência (mesma lib que assina/valida os
 * tokens da própria aplicação). O JWKS do Google roda em cache no Redis (as
 * chaves trocam raramente, não vale buscar a cada login).
 */
final readonly class GoogleJwksIdTokenVerifier implements GoogleIdTokenVerifier
{
    private const string JWKS_URL = 'https://www.googleapis.com/oauth2/v3/certs';
    private const string CACHE_KEY = 'google:jwks';
    private const int CACHE_TTL_SECONDS = 3600;
    private const array ALLOWED_ISSUERS = ['accounts.google.com', 'https://accounts.google.com'];

    public function __construct(
        private string $clientId,
        private RedisConnection $redis,
    ) {
    }

    public function verify(string $idToken): GoogleIdentityClaims
    {
        $keys = JWK::parseKeySet($this->fetchJwks());

        try {
            $decoded = JWT::decode($idToken, $keys);
        } catch (\Throwable) {
            throw new DomainException('Invalid Google credential.', DomainErrorType::Unauthorized);
        }

        if (
            ($decoded->aud ?? null) !== $this->clientId
            || !in_array($decoded->iss ?? null, self::ALLOWED_ISSUERS, true)
            || !isset($decoded->sub, $decoded->email)
        ) {
            throw new DomainException('Invalid Google credential.', DomainErrorType::Unauthorized);
        }

        return new GoogleIdentityClaims(
            subject: $decoded->sub,
            email: $decoded->email,
            emailVerified: (bool) ($decoded->email_verified ?? false),
            name: $decoded->name ?? $decoded->email,
        );
    }

    /** @return array<string, mixed> */
    private function fetchJwks(): array
    {
        $client = $this->redis->client();
        $cached = $client->get(self::CACHE_KEY);

        if ($cached !== null) {
            return json_decode($cached, true, flags: JSON_THROW_ON_ERROR);
        }

        $raw = @file_get_contents(self::JWKS_URL, context: stream_context_create([
            'http' => ['timeout' => 5],
        ]));

        if ($raw === false) {
            throw new DomainException('Could not reach Google to verify the credential.', DomainErrorType::Unauthorized);
        }

        $client->setex(self::CACHE_KEY, self::CACHE_TTL_SECONDS, $raw);

        return json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
    }
}
