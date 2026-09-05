<?php

declare(strict_types=1);

namespace App\Domain\Auth\Ports;

use App\Domain\Auth\PasswordResetToken;

interface PasswordResetTokenRepository
{
    public function insert(PasswordResetToken $token): void;

    public function findByRawToken(string $rawToken): ?PasswordResetToken;

    public function markUsed(string $id): void;

    /** Um reset bem-sucedido invalida qualquer outro token pendente do mesmo usuário -- não deixa link antigo ainda válido depois de trocar a senha. */
    public function invalidateAllForUser(string $userId): void;
}
