<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Shared\Money;
use App\Domain\Shared\Trashable;
use App\Domain\Shared\TrashableStatus;
use App\Domain\Shared\TrashState;
use App\Domain\Vehicle\Ports\VehicleRepository;
use App\Domain\Vehicle\Vehicle;
use App\Domain\Vehicle\VehicleFilters;

final readonly class PostgresVehicleRepository implements VehicleRepository
{
    private const string COLUMNS = 'id, dealership_id, brand, model, version, year, price, description, status, trashed_by_dealership_trash, trashed_at, anonymized_at, created_at, updated_at';

    // `search_vector` fica de fora de propósito: coluna gerada não aceita escrita, e o hábito daqui é listar tudo.
    private const string PREFIXED_COLUMNS = 'v.id, v.dealership_id, v.brand, v.model, v.version, v.year, v.price, v.description, v.status, v.trashed_by_dealership_trash, v.trashed_at, v.anonymized_at, v.created_at, v.updated_at';

    private const string SEARCHABLE_NAME = "v.brand || ' ' || v.model || ' ' || coalesce(v.version, '')";

    public function __construct(private DatabaseConnection $connection)
    {
    }

    public function findById(string $id): ?Vehicle
    {
        return $this->hydrateOne(
            $this->connection->execute('SELECT ' . self::COLUMNS . ' FROM vehicles WHERE id = :id', ['id' => $id]),
        );
    }

    public function insert(Vehicle $vehicle): void
    {
        $this->connection->execute(<<<'SQL'
            INSERT INTO vehicles (
                id, dealership_id, brand, model, version, year, price, description, status,
                trashed_by_dealership_trash, trashed_at, anonymized_at, created_at, updated_at
            ) VALUES (
                :id, :dealership_id, :brand, :model, :version, :year, :price, :description, :status,
                :trashed_by_dealership_trash, :trashed_at, :anonymized_at, :created_at, :updated_at
            )
            SQL, $this->toParams($vehicle));
    }

    public function update(Vehicle $vehicle): void
    {
        $params = $this->toParams($vehicle);
        unset($params['created_at']);

        $this->connection->execute(<<<'SQL'
            UPDATE vehicles SET
                dealership_id = :dealership_id, brand = :brand, model = :model, version = :version,
                year = :year, price = :price, description = :description, status = :status,
                trashed_by_dealership_trash = :trashed_by_dealership_trash,
                trashed_at = :trashed_at, anonymized_at = :anonymized_at, updated_at = :updated_at
            WHERE id = :id
            SQL, $params);
    }

    public function search(VehicleFilters $filters, ?string $ownerUserId, int $limit, int $offset): array
    {
        [$where, $params] = $this->criteria($filters, $ownerUserId);
        $params['limit'] = $limit;
        $params['offset'] = $offset;

        return $this->hydrateAll($this->connection->execute(sprintf(<<<'SQL'
            SELECT %s
            FROM vehicles v
            WHERE %s
            ORDER BY %sv.created_at DESC, v.id DESC
            LIMIT :limit OFFSET :offset
            SQL, self::PREFIXED_COLUMNS, $where, $this->relevance($filters)), $params));
    }

    public function countSearch(VehicleFilters $filters, ?string $ownerUserId): int
    {
        [$where, $params] = $this->criteria($filters, $ownerUserId);

        // Mesmo WHERE, sem ORDER BY: a relevância só existe na ordenação, então o total não pode discordar da página.
        return (int) $this->connection->execute(
            sprintf('SELECT COUNT(*) FROM vehicles v WHERE %s', $where),
            $params,
        )->fetchColumn();
    }

    public function availableFilters(?string $ownerUserId): array
    {
        [$where, $params] = $this->criteria(new VehicleFilters(), $ownerUserId);

        $row = $this->connection->execute(sprintf(<<<'SQL'
            SELECT array_to_string(array_agg(DISTINCT v.brand ORDER BY v.brand), ',') AS brands,
                   array_to_string(array_agg(DISTINCT v.model ORDER BY v.model), ',') AS models,
                   array_to_string(array_agg(DISTINCT v.year ORDER BY v.year DESC) FILTER (WHERE v.year IS NOT NULL), ',') AS years
            FROM vehicles v
            WHERE %s
            SQL, $where), $params)->fetch();

        if ($row === false) {
            return ['brands' => [], 'models' => [], 'years' => []];
        }

        $values = Row::from($row);

        return [
            'brands' => $this->split($values->nullableString('brands')),
            'models' => $this->split($values->nullableString('models')),
            'years' => array_map(intval(...), $this->split($values->nullableString('years'))),
        ];
    }

    public function trash(string $id): void
    {
        $this->connection->execute(<<<'SQL'
            UPDATE vehicles SET
                status = 'trashed', trashed_at = now(), trashed_by_dealership_trash = false, updated_at = now()
            WHERE id = :id
            SQL, ['id' => $id]);
    }

    public function restore(string $id): void
    {
        $this->connection->execute(<<<'SQL'
            UPDATE vehicles SET
                status = 'active', trashed_at = NULL, trashed_by_dealership_trash = false, updated_at = now()
            WHERE id = :id
            SQL, ['id' => $id]);
    }

    public function findTrashed(): array
    {
        return $this->hydrateAll(
            $this->connection->execute('SELECT ' . self::COLUMNS . " FROM vehicles WHERE status = 'trashed' AND anonymized_at IS NULL"),
        );
    }

    public function purge(Trashable $entity): void
    {
        if ($entity instanceof Vehicle) {
            $this->update($entity);
        }
    }

    public function trashAllInDealership(string $dealershipId): void
    {
        $this->connection->execute(<<<'SQL'
            UPDATE vehicles SET
                status = 'trashed', trashed_at = now(), trashed_by_dealership_trash = true, updated_at = now()
            WHERE dealership_id = :dealership_id AND status = 'active'
            SQL, ['dealership_id' => $dealershipId]);
    }

    public function restoreAutoTrashedInDealership(string $dealershipId): void
    {
        $this->connection->execute(<<<'SQL'
            UPDATE vehicles SET
                status = 'active', trashed_at = NULL, trashed_by_dealership_trash = false, updated_at = now()
            WHERE dealership_id = :dealership_id AND status = 'trashed' AND trashed_by_dealership_trash = true
            SQL, ['dealership_id' => $dealershipId]);
    }

    public function trashAllOwnedByUser(string $ownerUserId): void
    {
        $this->connection->execute(<<<'SQL'
            UPDATE vehicles SET
                status = 'trashed', trashed_at = now(), trashed_by_dealership_trash = true, updated_at = now()
            WHERE status = 'active'
              AND dealership_id IN (SELECT id FROM dealerships WHERE owner_user_id = :owner_user_id)
            SQL, ['owner_user_id' => $ownerUserId]);
    }

    public function restoreAutoTrashedOwnedByUser(string $ownerUserId): void
    {
        $this->connection->execute(<<<'SQL'
            UPDATE vehicles SET
                status = 'active', trashed_at = NULL, trashed_by_dealership_trash = false, updated_at = now()
            WHERE status = 'trashed' AND trashed_by_dealership_trash = true
              AND dealership_id IN (SELECT id FROM dealerships WHERE owner_user_id = :owner_user_id)
            SQL, ['owner_user_id' => $ownerUserId]);
    }

    /**
     * Um WHERE só pra listar, buscar e contar. Filtro ausente vira `IS NULL` no parâmetro e some
     * da conta, então "sem filtro nenhum" é literalmente a listagem de antes.
     *
     * @return array{0: string, 1: array<string, string|int|null>}
     */
    private function criteria(VehicleFilters $filters, ?string $ownerUserId): array
    {
        $conditions = ["v.status <> 'deleted'"];
        $params = [];

        if ($ownerUserId !== null) {
            $conditions[] = 'EXISTS (SELECT 1 FROM dealerships d WHERE d.id = v.dealership_id AND d.owner_user_id = :owner_user_id::uuid)';
            $params['owner_user_id'] = $ownerUserId;
        }

        // `websearch_to_tsquery` e não `to_tsquery`: a segunda lança erro de sintaxe com `&`, `|` ou `!`
        // vindos de caixa de busca, o que viraria 500. E `%` como operador, porque só ele usa o índice trigram.
        if ($filters->term !== null) {
            $conditions[] = sprintf(
                "(v.search_vector @@ websearch_to_tsquery('simple', :term) OR (%s) %% :term)",
                self::SEARCHABLE_NAME,
            );
            $params['term'] = $filters->term;
        }

        foreach (['brand' => $filters->brand, 'model' => $filters->model] as $field => $value) {
            if ($value !== null) {
                $conditions[] = sprintf("v.search_vector @@ websearch_to_tsquery('simple', :%s)", $field);
                $params[$field] = $value;
            }
        }

        $ranges = [
            'year_min' => ['v.year >= :year_min', $filters->yearMin],
            'year_max' => ['v.year <= :year_max', $filters->yearMax],
            'price_min' => ['v.price >= :price_min::numeric', $filters->priceMin?->toDecimal()],
            'price_max' => ['v.price <= :price_max::numeric', $filters->priceMax?->toDecimal()],
            'dealership_id' => ['v.dealership_id = :dealership_id::uuid', $filters->dealershipId],
        ];

        foreach ($ranges as $name => [$condition, $value]) {
            if ($value !== null) {
                $conditions[] = $condition;
                $params[$name] = $value;
            }
        }

        return [implode(' AND ', $conditions), $params];
    }

    /**
     * Relevância no ORDER BY e nunca no WHERE: é o que mantém `COUNT(*)` coerente com a página.
     * O desempate por `id` fica no chamador, porque OFFSET sem ele duplica e pula linha, calado.
     */
    private function relevance(VehicleFilters $filters): string
    {
        $scores = [];

        if ($filters->term !== null) {
            $scores[] = sprintf(
                "ts_rank(v.search_vector, websearch_to_tsquery('simple', :term)) + similarity(%s, :term)",
                self::SEARCHABLE_NAME,
            );
        }

        // Filtrar por marca também ordena: quem é da marca vem antes de quem só a cita na descrição.
        foreach (['brand' => $filters->brand, 'model' => $filters->model] as $field => $value) {
            if ($value !== null) {
                $scores[] = sprintf("ts_rank(v.search_vector, websearch_to_tsquery('simple', :%s))", $field);
            }
        }

        return $scores === [] ? '' : implode(' + ', $scores) . ' DESC, ';
    }

    /** @return list<string> */
    private function split(?string $joined): array
    {
        return $joined === null || $joined === '' ? [] : explode(',', $joined);
    }

    private function hydrateOne(\PDOStatement $statement): ?Vehicle
    {
        $row = $statement->fetch();

        return $row === false ? null : $this->fromRow(Row::from($row));
    }

    /** @return list<Vehicle> */
    private function hydrateAll(\PDOStatement $statement): array
    {
        return array_values(array_map(fn (mixed $row): Vehicle => $this->fromRow(Row::from($row)), $statement->fetchAll()));
    }

    private function fromRow(Row $row): Vehicle
    {
        return new Vehicle(
            id: $row->string('id'),
            dealershipId: $row->string('dealership_id'),
            brand: $row->string('brand'),
            model: $row->string('model'),
            version: $row->nullableString('version'),
            year: $row->nullableInt('year'),
            // `numeric` sai do PDO como string e é assim que fica: converter pra float perderia centavo.
            price: Money::fromDecimal($row->string('price')),
            description: $row->nullableString('description'),
            trash: new TrashState(
                status: $row->enum(TrashableStatus::class, 'status'),
                trashedAt: $row->nullableDateTime('trashed_at'),
                anonymizedAt: $row->nullableDateTime('anonymized_at'),
            ),
            trashedByDealershipTrash: $row->bool('trashed_by_dealership_trash'),
            createdAt: $row->dateTime('created_at'),
            updatedAt: $row->dateTime('updated_at'),
        );
    }

    /** @return array<string, string|int|bool|null> */
    private function toParams(Vehicle $vehicle): array
    {
        return [
            'id' => $vehicle->id,
            'dealership_id' => $vehicle->dealershipId,
            'brand' => $vehicle->brand,
            'model' => $vehicle->model,
            'version' => $vehicle->version,
            'year' => $vehicle->year,
            'price' => $vehicle->price->toDecimal(),
            'description' => $vehicle->description,
            'status' => $vehicle->trash->status->value,
            'trashed_by_dealership_trash' => $vehicle->trashedByDealershipTrash,
            'trashed_at' => $vehicle->trash->trashedAt?->format(DATE_ATOM),
            'anonymized_at' => $vehicle->trash->anonymizedAt?->format(DATE_ATOM),
            'created_at' => $vehicle->createdAt->format(DATE_ATOM),
            'updated_at' => $vehicle->updatedAt->format(DATE_ATOM),
        ];
    }
}
