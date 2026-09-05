<?php

declare(strict_types=1);

namespace Tests\Domain\Auth\ValueObjects;

use App\Domain\Auth\ValueObjects\AccessTokenClaims;
use App\Domain\Users\UserRole;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AccessTokenClaimsTest extends TestCase
{
    #[Test]
    public function issue_gera_um_jti_e_calcula_expires_at_a_partir_do_ttl(): void
    {
        $before = new \DateTimeImmutable();
        $claims = AccessTokenClaims::issue('user-1', 'autoschedule-web', UserRole::Customer, ['profile:read'], 900);
        $after = new \DateTimeImmutable();

        $this->assertNotSame('', $claims->jti);
        $this->assertGreaterThanOrEqual($before->modify('+900 seconds'), $claims->expiresAt);
        $this->assertLessThanOrEqual($after->modify('+900 seconds'), $claims->expiresAt);
    }

    #[Test]
    public function has_scope_reflete_a_lista_de_escopos(): void
    {
        $claims = AccessTokenClaims::issue('user-1', 'autoschedule-web', UserRole::Customer, ['profile:read'], 900);

        $this->assertTrue($claims->hasScope('profile:read'));
        $this->assertFalse($claims->hasScope('profile:write'));
    }

    #[Test]
    public function role_e_null_para_um_token_m2m(): void
    {
        $claims = AccessTokenClaims::issue('autoschedule-service', 'autoschedule-service', null, ['service:internal'], 900);

        $this->assertNull($claims->role);
    }
}
