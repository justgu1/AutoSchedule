<?php

declare(strict_types=1);

namespace App\Domain\Auth\ValueObjects;

/** Só o que o login social precisa do id_token do Google -- nunca guardado, só usado no momento do login. */
final class GoogleIdentityClaims
{
    public function __construct(
        public readonly string $subject,
        public readonly string $email,
        public readonly bool $emailVerified,
        public readonly string $name,
    ) {
    }
}
