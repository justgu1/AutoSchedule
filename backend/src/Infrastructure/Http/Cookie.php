<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

final readonly class Cookie
{
    public const string ACCESS_TOKEN = 'access_token';
    public const string REFRESH_TOKEN = 'refresh_token';
    public const string CSRF = 'XSRF-TOKEN';

    /** $maxAge em segundos; 0 = cookie de sessão, negativo = apaga o cookie. */
    public function __construct(
        public string $value,
        public int $maxAge = 0,
        public bool $httpOnly = true,
        public SameSite $sameSite = SameSite::Strict,
        public bool $secure = false,
    ) {
    }

    public function expiresAt(): int
    {
        return match (true) {
            $this->maxAge > 0 => time() + $this->maxAge,
            $this->maxAge < 0 => time() - 3600,
            default => 0,
        };
    }
}
