<?php

declare(strict_types=1);

namespace Tests\Domain\Auth;

use App\Domain\Auth\PasswordResetToken;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PasswordResetTokenTest extends TestCase
{
    #[Test]
    public function issue_nunca_guarda_o_token_em_texto_puro(): void
    {
        [$rawToken, $token] = PasswordResetToken::issue('user-uuid', 3600);

        $this->assertNotSame($rawToken, $token->tokenHash);
        $this->assertFalse($token->isUsed());
    }

    #[Test]
    public function esta_expirado_quando_o_ttl_e_negativo(): void
    {
        [, $token] = PasswordResetToken::issue('user-uuid', -1);

        $this->assertTrue($token->isExpired());
    }

    #[Test]
    public function nao_esta_expirado_dentro_do_ttl(): void
    {
        [, $token] = PasswordResetToken::issue('user-uuid', 3600);

        $this->assertFalse($token->isExpired());
    }
}
