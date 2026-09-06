<?php

declare(strict_types=1);

namespace Tests\Domain\Users;

use App\Domain\Users\User;
use App\Domain\Users\UserRole;
use App\Domain\Users\UserStatus;
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
        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertNull($user->anonymizedAt);
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
        $this->assertSame(UserStatus::Deleted, $anonymized->status);
        $this->assertNotNull($anonymized->anonymizedAt);
    }

    #[Test]
    public function with_profile_troca_nome_e_telefone_mas_preserva_o_resto(): void
    {
        $user = User::register('Ada', 'ada@example.com', '+55 11 90000-0000', 'secret', UserRole::Customer);

        $updated = $user->withProfile('Ada Lovelace', null);

        $this->assertSame('Ada Lovelace', $updated->name);
        $this->assertNull($updated->phone);
        $this->assertSame($user->id, $updated->id);
        $this->assertSame($user->email, $updated->email);
        $this->assertSame($user->passwordHash, $updated->passwordHash);
    }

    #[Test]
    public function with_new_password_troca_o_hash_mas_a_senha_antiga_para_de_validar(): void
    {
        $user = User::register('Ada', 'ada@example.com', null, 'old-password', UserRole::Customer);

        $updated = $user->withNewPassword('new-password');

        $this->assertNotSame($user->passwordHash, $updated->passwordHash);
        $this->assertTrue($updated->verifyPassword('new-password'));
        $this->assertFalse($updated->verifyPassword('old-password'));
    }

    #[Test]
    public function is_eligible_for_self_service_role_change_permite_so_customer_virando_seller(): void
    {
        $customer = User::register('Ada', 'ada@example.com', null, 'secret', UserRole::Customer);

        $this->assertTrue($customer->isEligibleForSelfServiceRoleChange(UserRole::Seller));
        $this->assertFalse($customer->isEligibleForSelfServiceRoleChange(UserRole::Admin));
        $this->assertFalse($customer->isEligibleForSelfServiceRoleChange(UserRole::Customer));
    }

    #[Test]
    public function is_eligible_for_self_service_role_change_rejeita_a_partir_de_seller_ou_admin(): void
    {
        $seller = User::register('Ada', 'ada@example.com', null, 'secret', UserRole::Seller);
        $admin = User::register('Bob', 'bob@example.com', null, 'secret', UserRole::Admin);

        $this->assertFalse($seller->isEligibleForSelfServiceRoleChange(UserRole::Customer));
        $this->assertFalse($seller->isEligibleForSelfServiceRoleChange(UserRole::Admin));
        $this->assertFalse($admin->isEligibleForSelfServiceRoleChange(UserRole::Seller));
    }

    #[Test]
    public function with_role_troca_o_role_mas_preserva_o_resto(): void
    {
        $user = User::register('Ada', 'ada@example.com', null, 'secret', UserRole::Customer);

        $updated = $user->withRole(UserRole::Admin);

        $this->assertSame(UserRole::Admin, $updated->role);
        $this->assertSame($user->id, $updated->id);
        $this->assertSame($user->name, $updated->name);
        $this->assertSame($user->passwordHash, $updated->passwordHash);
    }

    #[Test]
    public function is_eligible_for_restore_permite_so_trashed_ainda_nao_anonimizado(): void
    {
        $active = User::register('Ada', 'ada@example.com', null, 'secret', UserRole::Customer);
        $trashed = $this->trashedFixture();
        $alreadyAnonymized = $this->trashedFixture(anonymizedAt: new \DateTimeImmutable());

        $this->assertFalse($active->isEligibleForRestore());
        $this->assertTrue($trashed->isEligibleForRestore());
        $this->assertFalse($alreadyAnonymized->isEligibleForRestore());
    }

    #[Test]
    public function is_eligible_for_purge_exige_trashed_ha_mais_de_grace_days_e_ainda_nao_anonimizado(): void
    {
        $now = new \DateTimeImmutable();
        $active = User::register('Ada', 'ada@example.com', null, 'secret', UserRole::Customer);
        $recentlyTrashed = $this->trashedFixture(deletedAt: $now->modify('-5 days'));
        $longTrashed = $this->trashedFixture(deletedAt: $now->modify('-31 days'));
        $alreadyAnonymized = $this->trashedFixture(deletedAt: $now->modify('-31 days'), anonymizedAt: $now);

        $this->assertFalse($active->isEligibleForPurge(30, $now));
        $this->assertFalse($recentlyTrashed->isEligibleForPurge(30, $now));
        $this->assertTrue($longTrashed->isEligibleForPurge(30, $now));
        $this->assertFalse($alreadyAnonymized->isEligibleForPurge(30, $now));
    }

    private function trashedFixture(?\DateTimeImmutable $deletedAt = null, ?\DateTimeImmutable $anonymizedAt = null): User
    {
        $user = User::register('Ada', 'ada@example.com', null, 'secret', UserRole::Customer);

        return new User(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            phone: $user->phone,
            passwordHash: $user->passwordHash,
            role: $user->role,
            passwordSetAt: $user->passwordSetAt,
            emailVerifiedAt: $user->emailVerifiedAt,
            createdAt: $user->createdAt,
            updatedAt: $user->updatedAt,
            deletedAt: $deletedAt ?? new \DateTimeImmutable(),
            status: UserStatus::Trashed,
            anonymizedAt: $anonymizedAt,
        );
    }
}
