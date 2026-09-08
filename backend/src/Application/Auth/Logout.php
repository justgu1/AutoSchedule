<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Domain\Auth\Ports\RefreshTokenRepository;
use App\Domain\Auth\RefreshToken;

/** Idempotente: token inexistente não é erro, só não tem mais nada a revogar. */
final readonly class Logout
{
    public function __construct(private RefreshTokenRepository $refreshTokens)
    {
    }

    public function __invoke(string $rawRefreshToken): void
    {
        $current = $this->refreshTokens->findByRawToken($rawRefreshToken);

        if ($current instanceof RefreshToken) {
            $this->refreshTokens->revokeFamily($current->familyId);
        }
    }
}
