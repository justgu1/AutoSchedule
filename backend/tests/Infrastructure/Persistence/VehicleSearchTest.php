<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Persistence;

use App\Domain\Shared\Money;
use App\Domain\Vehicle\BodyType;
use App\Domain\Vehicle\FuelType;
use App\Domain\Vehicle\Transmission;
use App\Domain\Vehicle\Vehicle;
use App\Domain\Vehicle\VehicleFilters;
use App\Domain\Vehicle\VehicleSort;
use App\Infrastructure\Persistence\PostgresVehicleRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;

/** A busca só existe no banco (coluna gerada, FTS e trigram), então não há teste puro que a cubra. */
#[Group('integration')]
final class VehicleSearchTest extends TestCase
{
    private \PDO $pdo;
    private PostgresVehicleRepository $repository;
    private string $ownerId;
    private string $dealershipId;

    protected function setUp(): void
    {
        $connection = TestDatabase::connect();
        $this->pdo = $connection->pdo();

        $this->pdo->beginTransaction();
        $this->repository = new PostgresVehicleRepository($connection);
        $this->ownerId = $this->insertSellerUser();
        $this->dealershipId = $this->insertDealership($this->ownerId);
    }

    protected function tearDown(): void
    {
        $this->pdo->rollBack();
    }

    #[Test]
    public function busca_por_marca_encontra_o_veiculo_pelo_indice_de_texto(): void
    {
        $onix = $this->register('Chevrolet', 'Onix');
        $this->register('Fiat', 'Argo');

        $this->assertSame([$onix->id], $this->idsOf(new VehicleFilters(term: 'chevrolet')));
    }

    /** O motivo de a busca ser configurada no banco: filtro de marca tem que alcançar o texto livre. */
    #[Test]
    public function filtro_de_marca_encontra_o_veiculo_que_so_cita_a_marca_na_descricao(): void
    {
        $mentioned = $this->register('Fiat', 'Argo', description: 'Trocado por um Chevrolet seminovo');
        $named = $this->register('Chevrolet', 'Onix');

        $ids = $this->idsOf(new VehicleFilters(brand: 'chevrolet'));

        $this->assertContains($mentioned->id, $ids);
        $this->assertContains($named->id, $ids);
    }

    /** Peso A no campo próprio, D na descrição: quem tem a marca no campo vem primeiro. */
    #[Test]
    public function campo_proprio_ranqueia_acima_da_descricao(): void
    {
        $this->register('Fiat', 'Argo', description: 'Chevrolet Chevrolet Chevrolet');
        $named = $this->register('Chevrolet', 'Onix');

        $this->assertSame($named->id, $this->idsOf(new VehicleFilters(term: 'chevrolet'))[0]);
    }

    /** Filtrar não é só recortar: quem é da marca aparece antes de quem só a cita. */
    #[Test]
    public function filtro_de_marca_tambem_ordena_pelo_campo_proprio(): void
    {
        $mentioned = $this->register('Fiat', 'Argo', description: 'Troco por um Chevrolet');
        $named = $this->register('Chevrolet', 'Onix');

        $ids = $this->idsOf(new VehicleFilters(brand: 'chevrolet'));

        $this->assertSame([$named->id, $mentioned->id], $ids);
    }

    #[Test]
    public function busca_encontra_mesmo_com_erro_de_digitacao_na_marca(): void
    {
        $corolla = $this->register('Toyota', 'Corolla');

        $this->assertSame([$corolla->id], $this->idsOf(new VehicleFilters(term: 'corola')));
    }

    /** `to_tsquery` lançaria erro de sintaxe aqui e viraria 500 vindo de caixa de busca. */
    #[Test]
    public function busca_com_caractere_especial_nao_quebra(): void
    {
        $vehicle = $this->register('Volkswagen', 'T-Cross');

        $this->assertNotContains($vehicle->id, $this->idsOf(new VehicleFilters(term: 'onix & !fiat | :')));
        $this->assertContains($vehicle->id, $this->idsOf(new VehicleFilters(term: 'volkswagen | :')));
    }

    /** O bug relatado: prefixo curto de uma palavra só presente em `description` não achava nada antes desta correção. */
    #[Test]
    public function busca_por_prefixo_na_descricao_encontra_progressivamente(): void
    {
        $vehicle = $this->register('Fiat', 'Argo', description: 'Anúncio de teste, sem uso');

        foreach (['t', 'te', 'tes', 'test', 'teste'] as $prefix) {
            $this->assertContains($vehicle->id, $this->idsOf(new VehicleFilters(term: $prefix)), "prefixo '{$prefix}'");
        }
    }

    #[Test]
    public function busca_por_substring_no_meio_da_palavra_na_descricao(): void
    {
        $vehicle = $this->register('Fiat', 'Argo', description: 'Testemunha ocular do estado de conservação');

        $this->assertContains($vehicle->id, $this->idsOf(new VehicleFilters(term: 'estem')));
    }

    #[Test]
    public function termo_de_um_caractere_nao_quebra_a_busca(): void
    {
        $this->register('Fiat', 'Argo');

        $this->assertGreaterThanOrEqual(0, count($this->idsOf(new VehicleFilters(term: 'a'))));
    }

    #[Test]
    public function busca_vazia_devolve_a_listagem_inteira(): void
    {
        $this->register('Chevrolet', 'Onix');
        $this->register('Fiat', 'Argo');

        $this->assertCount(2, $this->idsOf(new VehicleFilters()));
    }

    #[Test]
    public function filtros_empilhados_se_combinam(): void
    {
        $wanted = $this->register('Chevrolet', 'Onix', year: 2023, price: new Money(8990000));
        $this->register('Chevrolet', 'Onix', year: 2015, price: new Money(4000000));
        $this->register('Fiat', 'Argo', year: 2023, price: new Money(8990000));

        $ids = $this->idsOf(new VehicleFilters(
            brand: 'chevrolet',
            yearMin: 2020,
            priceMin: new Money(5000000),
            priceMax: new Money(9000000),
        ));

        $this->assertSame([$wanted->id], $ids);
    }

    /** Os 3 specs (câmbio/carroceria/combustível) e o teto de km combinam por AND, igual marca/ano/preço. */
    #[Test]
    public function filtro_de_specs_combina_cambio_carroceria_combustivel_e_km(): void
    {
        $wanted = $this->register(
            'Toyota',
            'Corolla Cross',
            transmission: Transmission::Automatic,
            bodyType: BodyType::Suv,
            fuelType: FuelType::Hybrid,
            mileageKm: 20000,
        );
        // Cada um erra o filtro por um critério só, provando que todos os quatro são de fato exigidos.
        $this->register('Toyota', 'Corolla Cross', transmission: Transmission::Manual, bodyType: BodyType::Suv, fuelType: FuelType::Hybrid, mileageKm: 20000);
        $this->register('Toyota', 'Corolla Cross', transmission: Transmission::Automatic, bodyType: BodyType::Sedan, fuelType: FuelType::Hybrid, mileageKm: 20000);
        $this->register('Toyota', 'Corolla Cross', transmission: Transmission::Automatic, bodyType: BodyType::Suv, fuelType: FuelType::Flex, mileageKm: 20000);
        $this->register('Toyota', 'Corolla Cross', transmission: Transmission::Automatic, bodyType: BodyType::Suv, fuelType: FuelType::Hybrid, mileageKm: 90000);

        $ids = $this->idsOf(new VehicleFilters(
            transmission: Transmission::Automatic,
            bodyType: BodyType::Suv,
            fuelType: FuelType::Hybrid,
            mileageKmMax: 30000,
        ));

        $this->assertSame([$wanted->id], $ids);
    }

    #[Test]
    public function faixa_de_ano_recorta_o_resultado_pelas_duas_pontas(): void
    {
        $this->register('Chevrolet', 'Onix', year: 2015);
        $middle = $this->register('Chevrolet', 'Onix', year: 2020);
        $this->register('Chevrolet', 'Onix', year: 2024);

        $this->assertSame([$middle->id], $this->idsOf(new VehicleFilters(yearMin: 2018, yearMax: 2022)));
    }

    /** Coluna gerada não pode ser esquecida: o update não recalcula nada à mão. */
    #[Test]
    public function search_vector_e_recalculado_quando_a_descricao_muda(): void
    {
        $vehicle = $this->register('Fiat', 'Argo');
        $this->assertSame([], $this->idsOf(new VehicleFilters(term: 'blindado')));

        $this->repository->update($vehicle->withDetails(
            brand: 'Fiat',
            model: 'Argo',
            version: null,
            manufactureYear: null,
            modelYear: null,
            price: $vehicle->price,
            description: 'Carro blindado',
            mileageKm: null,
            transmission: null,
            bodyType: null,
            fuelType: null,
            color: null,
            plateEndDigit: null,
            acceptsTrade: false,
            ipvaPaid: false,
            licensed: false,
        ));

        $this->assertSame([$vehicle->id], $this->idsOf(new VehicleFilters(term: 'blindado')));
    }

    /** Rank empata em muita linha, e OFFSET sem desempate duplica e pula linha entre páginas. */
    #[Test]
    public function paginacao_da_busca_nao_repete_nem_pula_linha_entre_paginas(): void
    {
        for ($i = 0; $i < 6; ++$i) {
            $this->register('Chevrolet', 'Onix');
        }

        $filters = new VehicleFilters(term: 'onix');
        $first = $this->idsOf($filters, 3, 0);
        $second = $this->idsOf($filters, 3, 3);

        $this->assertCount(3, $first);
        $this->assertCount(3, $second);
        $this->assertSame([], array_intersect($first, $second));
    }

    #[Test]
    public function busca_nao_atravessa_a_concessionaria_de_outro_seller(): void
    {
        $mine = $this->register('Chevrolet', 'Onix');
        $otherDealership = $this->insertDealership($this->insertSellerUser());
        $this->register('Chevrolet', 'Onix', dealershipId: $otherDealership);

        $ids = array_map(
            static fn (Vehicle $v): string => $v->id,
            $this->repository->search(new VehicleFilters(term: 'onix'), $this->ownerId, 10, 0),
        );

        $this->assertSame([$mine->id], $ids);
    }

    #[Test]
    public function facetas_trazem_so_o_que_existe_em_estoque(): void
    {
        $this->register('Chevrolet', 'Onix', year: 2023);
        $this->register('Fiat', 'Argo', year: 2020);
        $this->register('Chevrolet', 'Onix', year: 2023);

        $filters = $this->repository->availableFilters($this->ownerId);

        $this->assertSame(['Chevrolet', 'Fiat'], $filters['brands']);
        $this->assertSame(['Argo', 'Onix'], $filters['models']);
        $this->assertSame([2023, 2020], $filters['years']);
    }

    /** O catálogo público não é `search()` com filtro a mais -- ele reforça `active` na query, sem depender só do RLS. */
    #[Test]
    public function catalogo_publico_traz_veiculo_de_qualquer_dono_mas_so_ativo(): void
    {
        $mine = $this->register('Chevrolet', 'Onix');
        $otherOwnerDealership = $this->insertDealership($this->insertSellerUser());
        $othersVehicle = $this->register('Fiat', 'Argo', dealershipId: $otherOwnerDealership);
        $trashed = $this->register('Toyota', 'Corolla');
        $this->repository->trash($trashed->id);

        $ids = array_map(
            static fn (Vehicle $v): string => $v->id,
            $this->repository->searchPublic(new VehicleFilters(), 10, 0),
        );

        $this->assertContains($mine->id, $ids);
        $this->assertContains($othersVehicle->id, $ids);
        $this->assertNotContains($trashed->id, $ids);
        $this->assertSame(2, $this->repository->countSearchPublic(new VehicleFilters()));
    }

    #[Test]
    public function catalogo_publico_esconde_veiculo_ativo_de_concessionaria_na_lixeira(): void
    {
        $vehicle = $this->register('Chevrolet', 'Onix');
        $this->pdo->prepare('UPDATE dealerships SET status = ? WHERE id = ?')->execute(['trashed', $this->dealershipId]);

        $ids = array_map(
            static fn (Vehicle $v): string => $v->id,
            $this->repository->searchPublic(new VehicleFilters(), 10, 0),
        );

        $this->assertNotContains($vehicle->id, $ids);
    }

    #[Test]
    public function facetas_publicas_ignoram_veiculo_trashed(): void
    {
        $this->register('Chevrolet', 'Onix');
        $trashed = $this->register('Toyota', 'Corolla');
        $this->repository->trash($trashed->id);

        $filters = $this->repository->availableFiltersPublic();

        $this->assertSame(['Chevrolet'], $filters['brands']);
    }

    #[Test]
    public function ordenacao_explicita_por_preco_ignora_a_relevancia(): void
    {
        $cheap = $this->register('Chevrolet', 'Onix', price: new Money(5000000));
        $expensive = $this->register('Chevrolet', 'Onix', price: new Money(9000000));

        $this->assertSame([$expensive->id, $cheap->id], $this->idsOf(new VehicleFilters(sort: VehicleSort::PriceDesc)));
        $this->assertSame([$cheap->id, $expensive->id], $this->idsOf(new VehicleFilters(sort: VehicleSort::PriceAsc)));
    }

    #[Test]
    public function ordenacao_explicita_por_ano_traz_os_mais_novos_primeiro(): void
    {
        $older = $this->register('Chevrolet', 'Onix', year: 2015);
        $newer = $this->register('Chevrolet', 'Onix', year: 2024);

        $this->assertSame([$newer->id, $older->id], $this->idsOf(new VehicleFilters(sort: VehicleSort::YearDesc)));
    }

    #[Test]
    public function ordenacao_explicita_por_criacao_recente_e_antiga(): void
    {
        $first = $this->register('Chevrolet', 'Onix');
        $second = $this->register('Chevrolet', 'Onix');

        $this->assertSame([$second->id, $first->id], $this->idsOf(new VehicleFilters(sort: VehicleSort::CreatedDesc)));
        $this->assertSame([$first->id, $second->id], $this->idsOf(new VehicleFilters(sort: VehicleSort::CreatedAsc)));
    }

    /** @return list<string> */
    private function idsOf(VehicleFilters $filters, int $limit = 10, int $offset = 0): array
    {
        return array_map(
            static fn (Vehicle $v): string => $v->id,
            $this->repository->search($filters, null, $limit, $offset),
        );
    }

    private function register(
        string $brand,
        string $model,
        ?int $year = 2023,
        ?Money $price = null,
        ?string $description = null,
        ?string $dealershipId = null,
        ?Transmission $transmission = null,
        ?BodyType $bodyType = null,
        ?FuelType $fuelType = null,
        ?int $mileageKm = null,
    ): Vehicle {
        $vehicle = Vehicle::register(
            dealershipId: $dealershipId ?? $this->dealershipId,
            brand: $brand,
            model: $model,
            version: null,
            manufactureYear: $year,
            modelYear: $year,
            price: $price ?? new Money(8990000),
            description: $description,
            mileageKm: $mileageKm,
            transmission: $transmission,
            bodyType: $bodyType,
            fuelType: $fuelType,
        );
        $this->repository->insert($vehicle);

        return $vehicle;
    }

    private function insertSellerUser(): string
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            INSERT INTO users (name, email, password, role)
            VALUES ('Seller Test', :email, 'hash', 'seller')
            RETURNING id
            SQL);
        $statement->execute(['email' => 'seller-' . bin2hex(random_bytes(8)) . '@example.com']);

        return (string) $statement->fetchColumn();
    }

    private function insertDealership(string $ownerUserId): string
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            INSERT INTO dealerships (owner_user_id, name, slug, zip_code, address, number, neighborhood, city, state)
            VALUES (:owner_user_id, 'Auto Center', :slug, '01000-000', 'Rua Antiga', '10', 'Bairro', 'Cidade', 'SP')
            RETURNING id
            SQL);
        $statement->execute(['owner_user_id' => $ownerUserId, 'slug' => 'auto-center-' . bin2hex(random_bytes(4))]);

        return (string) $statement->fetchColumn();
    }
}
