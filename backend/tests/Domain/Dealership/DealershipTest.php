<?php

declare(strict_types=1);

namespace Tests\Domain\Dealership;

use App\Domain\Dealership\Dealership;
use App\Domain\Shared\Address;
use App\Domain\Shared\TrashableStatus;
use App\Domain\Shared\TrashState;
use App\Domain\Shared\Uf;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DealershipTest extends TestCase
{
    #[Test]
    public function register_monta_uma_concessionaria_nova_ativa_e_sem_anonimizacao(): void
    {
        $dealership = $this->registerFixture();

        $this->assertNotSame('', $dealership->id);
        $this->assertSame('owner-1', $dealership->ownerUserId);
        $this->assertSame('Auto Center', $dealership->name);
        $this->assertSame(TrashableStatus::Active, $dealership->trash->status);
        $this->assertFalse($dealership->trashedByOwnerDeactivation);
        $this->assertNull($dealership->trash->trashedAt);
        $this->assertNull($dealership->trash->anonymizedAt);
    }

    /** Slug é URL amigável, nunca o id -- formato: nome normalizado + 6 caracteres do próprio id, sempre único por natureza. */
    #[Test]
    public function register_gera_um_slug_a_partir_do_nome_sem_expor_o_id_inteiro(): void
    {
        $dealership = Dealership::register(
            ownerUserId: 'owner-1',
            name: 'Auto Center Prime!',
            address: $this->addressFixture(),
            phone: null,
        );

        $this->assertMatchesRegularExpression('/^auto-center-prime-[0-9a-f]{6}$/', $dealership->slug);
        $this->assertStringNotContainsString($dealership->id, $dealership->slug);
    }

    #[Test]
    public function with_profile_troca_os_dados_mas_preserva_dono_status_e_slug(): void
    {
        $dealership = $this->registerFixture();

        $updated = $dealership->withProfile(
            name: 'Novo Nome',
            address: new Address('99999-999', 'Rua Nova', '42', 'Fundos', 'Centro', 'Nova Cidade', Uf::RJ),
            phone: '11999999999',
            email: 'novo@example.com',
        );

        $this->assertSame('Novo Nome', $updated->name);
        $this->assertSame('Rua Nova', $updated->address->street);
        $this->assertSame(Uf::RJ, $updated->address->state);
        $this->assertSame($dealership->id, $updated->id);
        $this->assertSame($dealership->ownerUserId, $updated->ownerUserId);
        $this->assertSame($dealership->trash->status, $updated->trash->status);
        $this->assertSame($dealership->slug, $updated->slug);
    }

    #[Test]
    public function with_owner_reassocia_o_dono_mas_preserva_o_resto(): void
    {
        $dealership = $this->registerFixture();

        $updated = $dealership->withOwner('owner-2');

        $this->assertSame('owner-2', $updated->ownerUserId);
        $this->assertSame($dealership->id, $updated->id);
        $this->assertSame($dealership->name, $updated->name);
    }

    #[Test]
    public function with_photo_substitui_a_referencia_mas_preserva_o_resto(): void
    {
        $dealership = $this->registerFixture();
        $this->assertNull($dealership->photoFileId);

        $withPhoto = $dealership->withPhoto('file-1');
        $this->assertSame('file-1', $withPhoto->photoFileId);
        $this->assertSame($dealership->id, $withPhoto->id);
        $this->assertSame($dealership->name, $withPhoto->name);

        $withoutPhoto = $withPhoto->withPhoto(null);
        $this->assertNull($withoutPhoto->photoFileId);
    }

    #[Test]
    public function is_eligible_for_restore_permite_so_trashed_ainda_nao_anonimizado(): void
    {
        $active = $this->registerFixture();
        $trashed = $this->trashedFixture();
        $alreadyAnonymized = $this->trashedFixture(anonymizedAt: new \DateTimeImmutable());

        $this->assertFalse($active->trash->allowsRestore());
        $this->assertTrue($trashed->trash->allowsRestore());
        $this->assertFalse($alreadyAnonymized->trash->allowsRestore());
    }

    #[Test]
    public function is_eligible_for_purge_exige_trashed_ha_mais_de_grace_days_e_ainda_nao_anonimizado(): void
    {
        $now = new \DateTimeImmutable();
        $active = $this->registerFixture();
        $recentlyTrashed = $this->trashedFixture(trashedAt: $now->modify('-5 days'));
        $longTrashed = $this->trashedFixture(trashedAt: $now->modify('-31 days'));
        $alreadyAnonymized = $this->trashedFixture(trashedAt: $now->modify('-31 days'), anonymizedAt: $now);

        $this->assertFalse($active->trash->allowsPurge($now));
        $this->assertFalse($recentlyTrashed->trash->allowsPurge($now));
        $this->assertTrue($longTrashed->trash->allowsPurge($now));
        $this->assertFalse($alreadyAnonymized->trash->allowsPurge($now));
    }

    #[Test]
    public function anonymized_escruba_identificador_direto_mas_preserva_localidade_agregada(): void
    {
        $dealership = $this->registerFixture()->withPhoto('file-1');

        $anonymized = $dealership->anonymized();

        $this->assertSame($dealership->id, $anonymized->id);
        $this->assertSame($dealership->ownerUserId, $anonymized->ownerUserId);
        $this->assertNotSame('Auto Center', $anonymized->name);
        $this->assertNotSame($dealership->slug, $anonymized->slug);
        $this->assertSame('', $anonymized->address->street);
        $this->assertSame('', $anonymized->address->number);
        $this->assertNull($anonymized->address->complement);
        $this->assertNull($anonymized->phone);
        $this->assertNull($anonymized->email);
        $this->assertSame($dealership->address->zipCode, $anonymized->address->zipCode);
        $this->assertSame($dealership->address->city, $anonymized->address->city);
        $this->assertSame($dealership->address->state, $anonymized->address->state);
        $this->assertSame(TrashableStatus::Deleted, $anonymized->trash->status);
        $this->assertNotNull($anonymized->trash->anonymizedAt);
        $this->assertNull($anonymized->photoFileId);
    }

    private function addressFixture(): Address
    {
        return new Address('01000-000', 'Rua Antiga', '10', null, 'Bairro', 'Cidade', Uf::SP);
    }

    private function registerFixture(): Dealership
    {
        return Dealership::register(
            ownerUserId: 'owner-1',
            name: 'Auto Center',
            address: $this->addressFixture(),
            phone: '11988888888',
        );
    }

    private function trashedFixture(?\DateTimeImmutable $trashedAt = null, ?\DateTimeImmutable $anonymizedAt = null): Dealership
    {
        $dealership = $this->registerFixture();

        return new Dealership(
            id: $dealership->id,
            ownerUserId: $dealership->ownerUserId,
            name: $dealership->name,
            slug: $dealership->slug,
            address: $dealership->address,
            phone: $dealership->phone,
            email: $dealership->email,
            photoFileId: $dealership->photoFileId,
            trash: new TrashState(TrashableStatus::Trashed, $trashedAt ?? new \DateTimeImmutable(), $anonymizedAt),
            trashedByOwnerDeactivation: false,
            createdAt: $dealership->createdAt,
            updatedAt: $dealership->updatedAt,
        );
    }
}
