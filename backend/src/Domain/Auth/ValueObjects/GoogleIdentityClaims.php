<?php

declare(strict_types=1);

namespace App\Domain\Auth\ValueObjects;

/** Só o que o login social precisa do id_token do Google -- nunca guardado, só usado no momento do login. */
final readonly class GoogleIdentityClaims
{
    public function __construct(
        public string $subject,
        public string $email,
        public bool $emailVerified,
        public string $name,
    ) {
    }
}
