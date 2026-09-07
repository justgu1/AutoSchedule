<?php

declare(strict_types=1);

namespace App\Domain\Auth\Ports;

use App\Domain\Auth\ValueObjects\AccessTokenClaims;

interface TokenIssuer
{
    public function issueAccessToken(AccessTokenClaims $claims): string;

    /** Assinatura, issuer/audience e claims obrigatórias caem todas no mesmo `DomainException(Unauthorized)`. */
    public function decodeAccessToken(string $token): AccessTokenClaims;
}
