<?php

declare(strict_types=1);

namespace App\Domain\Auth\DTO;

final readonly class TokenPair
{
    public function __construct(
        public string $accessToken,
        public int $expiresIn,
        /** @var list<string> */
        public array $scopes,
        public ?string $refreshToken = null,
        /** Login restaurou uma conta que estava na lixeira -- frontend avisa o usuário disso. */
        public bool $accountRestored = false,
    ) {
    }
}
