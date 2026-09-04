<?php

declare(strict_types=1);

namespace App\Domain\Auth\Ports;

use App\Domain\Auth\RefreshToken;

interface RefreshTokenRepository
{
    public function insert(RefreshToken $token): void;

    public function findByRawToken(string $rawToken): ?RefreshToken;

    /** Rotaciona $current pra $next na mesma família: marca $current revogado+substituído, insere $next -- um passo atômico. */
    public function rotate(RefreshToken $current, RefreshToken $next): void;

    /** Reuso detectado: revoga todo token da família, pra nenhum descendente de um token roubado continuar funcionando. */
    public function revokeFamily(string $familyId): void;
}
