<?php

declare(strict_types=1);

namespace Tests\Domain\Users;

use App\Domain\Users\User;
use App\Domain\Users\UserRole;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    #[Test]
    public function register_monta_um_usuario_novo_com_os_dados_informados_e_estado_inicial_correto(): void
    {
        $user = User::register('Ada Lovelace', 'ada@example.com', '+55 11 90000-0000', 'super-secret', UserRole::Customer);

        $this->assertNotSame('', $user->id);
        $this->assertSame('Ada Lovelace', $user->name);
        $this->assertSame('ada@example.com', $user->email);
        $this->assertSame(UserRole::Customer, $user->role);
        $this->assertNotNull($user->passwordSetAt);
        $this->assertNull($user->emailVerifiedAt);
        $this->assertNull($user->deletedAt);
    }

    #[Test]
    public function register_nao_guarda_a_senha_em_texto_puro(): void
    {
        $user = User::register('Ada', 'ada@example.com', null, 'super-secret', UserRole::Customer);

        $this->assertNotSame('super-secret', $user->passwordHash);
    }

    #[Test]
    public function verify_password_confere_a_senha_em_texto_puro_contra_o_hash(): void
    {
        $user = User::register('Ada', 'ada@example.com', null, 'correct-password', UserRole::Customer);

        $this->assertTrue($user->verifyPassword('correct-password'));
        $this->assertFalse($user->verifyPassword('wrong-password'));
    }

    #[Test]
    public function anonymized_remove_pii_mas_preserva_id_role_e_timestamps(): void
    {
        $user = User::register('Ada Lovelace', 'ada@example.com', '+55 11 90000-0000', 'secret', UserRole::Admin);

        $anonymized = $user->anonymized();

        $this->assertSame($user->id, $anonymized->id);
        $this->assertSame($user->role, $anonymized->role);
        $this->assertSame($user->passwordHash, $anonymized->passwordHash);
        $this->assertSame($user->createdAt, $anonymized->createdAt);
        $this->assertNotSame('Ada Lovelace', $anonymized->name);
        $this->assertNotSame('ada@example.com', $anonymized->email);
        $this->assertNull($anonymized->phone);
        $this->assertStringContainsString(substr($user->id, 0, 8), $anonymized->email);
    }
}
