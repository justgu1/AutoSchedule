<?php

declare(strict_types=1);

namespace App\Domain\Auth\Ports;

use App\Domain\Auth\ValueObjects\AccessTokenClaims;

interface TokenIssuer
{
    public function issueAccessToken(AccessTokenClaims $claims): string;

    /**
     * @throws \App\Domain\Exceptions\DomainException com DomainErrorType::Unauthorized
     *         pra qualquer token inválido, expirado ou malformado -- assinatura,
     *         issuer/audience e claims obrigatórias, tudo cai no mesmo resultado.
     */
    public function decodeAccessToken(string $token): AccessTokenClaims;
}
