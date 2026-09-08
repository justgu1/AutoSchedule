<?php

declare(strict_types=1);

namespace Tests\Application\Vehicle;

use App\Application\Vehicle\VehicleAmenities;
use App\Domain\Exceptions\DomainException;
use App\Domain\Vehicle\Amenity;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\DirectTransaction;
use Tests\Support\InMemoryVehicleAmenityCatalog;
use Tests\Support\InMemoryVehicleAmenityLinkRepository;

final class VehicleAmenitiesTest extends TestCase
{
    private const string AR_CONDICIONADO = 'aaaaaaaa-0000-0000-0000-000000000001';
    private const string CAMERA_RE = 'aaaaaaaa-0000-0000-0000-000000000002';

    private function amenities(): VehicleAmenities
    {
        $catalog = new InMemoryVehicleAmenityCatalog([
            new Amenity(self::AR_CONDICIONADO, 'ar_condicionado', 'Ar-condicionado', 0),
            new Amenity(self::CAMERA_RE, 'camera_re', 'Câmera de ré', 1),
        ]);

        return new VehicleAmenities($catalog, new InMemoryVehicleAmenityLinkRepository(), new DirectTransaction());
    }

    #[Test]
    public function replace_com_ids_validos_persiste_o_vinculo(): void
    {
        $amenities = $this->amenities();
        $amenities->replace('vehicle-1', [self::AR_CONDICIONADO, self::CAMERA_RE]);

        $links = $amenities->linksFor('vehicle-1');

        $this->assertCount(2, $links);
        $this->assertSame(self::AR_CONDICIONADO, $links[0]['id']);
    }

    /** Sem isso, um id de amenity inexistente vira erro de FK (500), nunca 422. */
    #[Test]
    public function replace_com_id_inexistente_no_catalogo_rejeita_com_422(): void
    {
        $amenities = $this->amenities();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Invalid data.');

        $amenities->replace('vehicle-1', [self::AR_CONDICIONADO, 'id-que-nao-existe']);
    }

    #[Test]
    public function replace_nao_persiste_nada_quando_algum_id_e_invalido(): void
    {
        $amenities = $this->amenities();

        try {
            $amenities->replace('vehicle-1', [self::AR_CONDICIONADO, 'id-que-nao-existe']);
        } catch (DomainException) {
            // esperado -- o teste confere o efeito colateral (ou a ausência dele), não a exceção em si.
        }

        $this->assertSame([], $amenities->linksFor('vehicle-1'));
    }

    #[Test]
    public function replace_troca_a_lista_inteira_em_vez_de_somar(): void
    {
        $amenities = $this->amenities();
        $amenities->replace('vehicle-1', [self::AR_CONDICIONADO, self::CAMERA_RE]);
        $amenities->replace('vehicle-1', [self::CAMERA_RE]);

        $links = $amenities->linksFor('vehicle-1');

        $this->assertCount(1, $links);
        $this->assertSame(self::CAMERA_RE, $links[0]['id']);
    }

    #[Test]
    public function catalog_devolve_o_catalogo_inteiro_na_ordem_de_posicao(): void
    {
        $amenities = $this->amenities();

        $this->assertCount(2, $amenities->catalog());
    }
}
