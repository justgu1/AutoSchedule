<?php

declare(strict_types=1);

namespace Tests\Domain\Auth;

use App\Domain\Auth\RefreshToken;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RefreshTokenTest extends TestCase
{
    #[Test]
    public function issue_nunca_guarda_o_token_em_texto_puro(): void
    {
        [$rawToken, $token] = RefreshToken::issue('client-uuid', 'user-uuid', ['profile:read'], 1_209_600);

        $this->assertNotSame($rawToken, $token->tokenHash);
        $this->assertFalse($token->isRevoked());
    }

    #[Test]
    public function rotate_mantem_a_mesma_familia_com_um_hash_novo(): void
    {
        [, $original] = RefreshToken::issue('client-uuid', 'user-uuid', ['profile:read'], 1_209_600);

        [$rawNext, $next] = $original->rotate(1_209_600);

        $this->assertSame($original->familyId, $next->familyId);
        $this->assertNotSame($rawNext, $next->tokenHash);
        $this->assertNotSame($original->tokenHash, $next->tokenHash);
    }

    #[Test]
    public function esta_expirado_quando_o_ttl_e_negativo(): void
    {
        [, $token] = RefreshToken::issue('client-uuid', 'user-uuid', [], -1);

        $this->assertTrue($token->isExpired());
    }
}
