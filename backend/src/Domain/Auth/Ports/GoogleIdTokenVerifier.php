<?php

declare(strict_types=1);

namespace App\Domain\Auth\Ports;

use App\Domain\Auth\ValueObjects\GoogleIdentityClaims;

interface GoogleIdTokenVerifier
{
    /** @throws \App\Domain\Exceptions\DomainException quando o token é inválido, expirado, ou tem assinatura/aud/iss errados */
    public function verify(string $idToken): GoogleIdentityClaims;
}
