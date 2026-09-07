<?php

declare(strict_types=1);

namespace Tests\Domain\Dealerships;

use App\Domain\Dealerships\Dealership;
use App\Domain\Shared\TrashableStatus;
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
        $this->assertSame(TrashableStatus::Active, $dealership->status);
        $this->assertFalse($dealership->trashedByOwnerDeactivation);
        $this->assertNull($dealership->trashedAt);
        $this->assertNull($dealership->anonymizedAt);
    }

    #[Test]
    public function with_profile_troca_os_dados_mas_preserva_dono_e_status(): void
    {
        $dealership = $this->registerFixture();

        $updated = $dealership->withProfile(
            name: 'Novo Nome',
            zipCode: '99999-999',
            address: 'Rua Nova',
            number: '42',
            complement: 'Fundos',
            neighborhood: 'Centro',
            city: 'Nova Cidade',
            state: 'SP',
            phone: '11999999999',
            latitude: null,
            longitude: null,
            googlePlaceId: null,
        );

        $this->assertSame('Novo Nome', $updated->name);
        $this->assertSame($dealership->id, $updated->id);
        $this->assertSame($dealership->ownerUserId, $updated->ownerUserId);
        $this->assertSame($dealership->status, $updated->status);
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

        $this->assertFalse($active->isEligibleForRestore());
        $this->assertTrue($trashed->isEligibleForRestore());
        $this->assertFalse($alreadyAnonymized->isEligibleForRestore());
    }

    #[Test]
    public function is_eligible_for_purge_exige_trashed_ha_mais_de_grace_days_e_ainda_nao_anonimizado(): void
    {
        $now = new \DateTimeImmutable();
        $active = $this->registerFixture();
        $recentlyTrashed = $this->trashedFixture(trashedAt: $now->modify('-5 days'));
        $longTrashed = $this->trashedFixture(trashedAt: $now->modify('-31 days'));
        $alreadyAnonymized = $this->trashedFixture(trashedAt: $now->modify('-31 days'), anonymizedAt: $now);

        $this->assertFalse($active->isEligibleForPurge(30, $now));
        $this->assertFalse($recentlyTrashed->isEligibleForPurge(30, $now));
        $this->assertTrue($longTrashed->isEligibleForPurge(30, $now));
        $this->assertFalse($alreadyAnonymized->isEligibleForPurge(30, $now));
    }

    #[Test]
    public function anonymized_escruba_identificador_direto_mas_preserva_geolocalizacao(): void
    {
        $dealership = $this->registerFixture()->withPhoto('file-1');

        $anonymized = $dealership->anonymized();

        $this->assertSame($dealership->id, $anonymized->id);
        $this->assertSame($dealership->ownerUserId, $anonymized->ownerUserId);
        $this->assertNotSame('Auto Center', $anonymized->name);
        $this->assertSame('', $anonymized->address);
        $this->assertSame('', $anonymized->number);
        $this->assertNull($anonymized->complement);
        $this->assertNull($anonymized->phone);
        $this->assertNull($anonymized->googlePlaceId);
        $this->assertSame($dealership->zipCode, $anonymized->zipCode);
        $this->assertSame($dealership->city, $anonymized->city);
        $this->assertSame($dealership->state, $anonymized->state);
        $this->assertSame(TrashableStatus::Deleted, $anonymized->status);
        $this->assertNotNull($anonymized->anonymizedAt);
        $this->assertNull($anonymized->photoFileId);
    }

    private function registerFixture(): Dealership
    {
        return Dealership::register(
            ownerUserId: 'owner-1',
            name: 'Auto Center',
            zipCode: '01000-000',
            address: 'Rua Antiga',
            number: '10',
            complement: null,
            neighborhood: 'Bairro',
            city: 'Cidade',
            state: 'SP',
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
            zipCode: $dealership->zipCode,
            address: $dealership->address,
            number: $dealership->number,
            complement: $dealership->complement,
            neighborhood: $dealership->neighborhood,
            city: $dealership->city,
            state: $dealership->state,
            latitude: $dealership->latitude,
            longitude: $dealership->longitude,
            googlePlaceId: $dealership->googlePlaceId,
            phone: $dealership->phone,
            photoFileId: $dealership->photoFileId,
            status: TrashableStatus::Trashed,
            trashedByOwnerDeactivation: false,
            trashedAt: $trashedAt ?? new \DateTimeImmutable(),
            anonymizedAt: $anonymizedAt,
            createdAt: $dealership->createdAt,
            updatedAt: $dealership->updatedAt,
        );
    }
}
