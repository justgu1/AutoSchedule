<?php

declare(strict_types=1);

namespace App\Domain\Auth\DTO;

final class TokenPair
{
    public function __construct(
        public readonly string $accessToken,
        public readonly int $expiresIn,
        /** @var list<string> */
        public readonly array $scopes,
        public readonly ?string $refreshToken = null,
    ) {
    }
}
