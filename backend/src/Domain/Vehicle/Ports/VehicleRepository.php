<?php

declare(strict_types=1);

namespace App\Domain\Vehicle\Ports;

use App\Domain\Shared\Ports\TrashableRepository;
use App\Domain\Vehicle\Vehicle;
use App\Domain\Vehicle\VehicleFilters;

interface VehicleRepository extends TrashableRepository
{
    public function findById(string $id): ?Vehicle;

    public function insert(Vehicle $vehicle): void;

    public function update(Vehicle $vehicle): void;

    /**
     * Listagem e busca são a mesma consulta: sem filtro nenhum ela degenera na listagem simples,
     * então não existem dois caminhos de SQL pra manter em sincronia.
     *
     * @param ?string $ownerUserId escopo do seller; `null` enxerga todo o estoque (admin)
     * @return list<Vehicle>
     */
    public function search(VehicleFilters $filters, ?string $ownerUserId, int $limit, int $offset): array;

    public function countSearch(VehicleFilters $filters, ?string $ownerUserId): int;

    /**
     * Marcas, modelos e anos que existem em estoque, pros filtros da tela não oferecerem
     * combinação que não devolve nada.
     *
     * @return array{brands: list<string>, models: list<string>, years: list<int>, transmissions: list<string>, body_types: list<string>, fuel_types: list<string>}
     */
    public function availableFilters(?string $ownerUserId): array;

    /**
     * O catálogo público: só `active`, só concessionária `active`, explícito na query mesmo com
     * o RLS por trás -- o banco reforça o que a aplicação já valida, nunca é a única linha de defesa.
     *
     * @return list<Vehicle>
     */
    public function searchPublic(VehicleFilters $filters, int $limit, int $offset): array;

    public function countSearchPublic(VehicleFilters $filters): int;

    /** @return array{brands: list<string>, models: list<string>, years: list<int>, transmissions: list<string>, body_types: list<string>, fuel_types: list<string>} */
    public function availableFiltersPublic(): array;

    public function trash(string $id): void;

    public function restore(string $id): void;

    public function trashAllInDealership(string $dealershipId): void;

    public function restoreAutoTrashedInDealership(string $dealershipId): void;

    /**
     * A desativação de conta arrasta concessionária e veículo de uma vez -- `TrashAccount` nunca passa pelo
     * caso de uso de concessionária, então a cascata de segundo nível precisa existir aqui.
     */
    public function trashAllOwnedByUser(string $ownerUserId): void;

    public function restoreAutoTrashedOwnedByUser(string $ownerUserId): void;
}
