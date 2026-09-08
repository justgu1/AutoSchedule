<?php

declare(strict_types=1);

namespace App\Application\Auth;

/** Os dois prazos andam juntos em todo fluxo de token; separados, eram seis leituras de config no boot. */
final readonly class TokenTtl
{
    public function __construct(
        public int $accessSeconds,
        public int $refreshSeconds,
    ) {
    }
}
