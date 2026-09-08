<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Shared\Money;
use App\Domain\Shared\Trashable;
use App\Domain\Shared\TrashableStatus;
use App\Domain\Shared\TrashState;
use App\Domain\Vehicle\BodyType;
use App\Domain\Vehicle\FuelType;
use App\Domain\Vehicle\Ports\VehicleRepository;
use App\Domain\Vehicle\Transmission;
use App\Domain\Vehicle\Vehicle;
use App\Domain\Vehicle\VehicleFilters;
use App\Domain\Vehicle\VehicleSort;

final readonly class PostgresVehicleRepository implements VehicleRepository
{
    private const string COLUMNS = 'id, dealership_id, brand, model, version, manufacture_year, model_year, price, description, mileage_km, transmission, body_type, fuel_type, color, plate_end_digit, accepts_trade, ipva_paid, licensed, status, trashed_by_dealership_trash, trashed_at, anonymized_at, created_at, updated_at';

    // `search_vector` fica de fora de propósito: coluna gerada não aceita escrita, e o hábito daqui é listar tudo.
    private const string PREFIXED_COLUMNS = 'v.id, v.dealership_id, v.brand, v.model, v.version, v.manufacture_year, v.model_year, v.price, v.description, v.mileage_km, v.transmission, v.body_type, v.fuel_type, v.color, v.plate_end_digit, v.accepts_trade, v.ipva_paid, v.licensed, v.status, v.trashed_by_dealership_trash, v.trashed_at, v.anonymized_at, v.created_at, v.updated_at';

    private const string SEARCHABLE_NAME = "v.brand || ' ' || v.model || ' ' || coalesce(v.version, '')";

    // Cobre também `description` -- índice trigram próprio (`vehicles_full_text_trgm_idx`), separado do de
    // SEARCHABLE_NAME pra não diluir a similaridade usada no ranking/tolerância a erro de digitação.
    private const string SEARCHABLE_FULL_TEXT = "v.brand || ' ' || v.model || ' ' || coalesce(v.version, '') || ' ' || coalesce(v.description, '')";

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
                id, dealership_id, brand, model, version, manufacture_year, model_year, price, description,
                mileage_km, transmission, body_type, fuel_type, color, plate_end_digit,
                accepts_trade, ipva_paid, licensed, status,
                trashed_by_dealership_trash, trashed_at, anonymized_at, created_at, updated_at
            ) VALUES (
                :id, :dealership_id, :brand, :model, :version, :manufacture_year, :model_year, :price, :description,
                :mileage_km, :transmission, :body_type, :fuel_type, :color, :plate_end_digit,
                :accepts_trade, :ipva_paid, :licensed, :status,
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
                manufacture_year = :manufacture_year, model_year = :model_year,
                price = :price, description = :description, status = :status,
                mileage_km = :mileage_km, transmission = :transmission, body_type = :body_type,
                fuel_type = :fuel_type, color = :color, plate_end_digit = :plate_end_digit,
                accepts_trade = :accepts_trade, ipva_paid = :ipva_paid, licensed = :licensed,
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
            ORDER BY %s
            LIMIT :limit OFFSET :offset
            SQL, self::PREFIXED_COLUMNS, $where, $this->orderBy($filters)), $params));
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

    public function searchPublic(VehicleFilters $filters, int $limit, int $offset): array
    {
        [$where, $params] = $this->criteria($filters, null, publicOnly: true);
        $params['limit'] = $limit;
        $params['offset'] = $offset;

        return $this->hydrateAll($this->connection->execute(sprintf(<<<'SQL'
            SELECT %s
            FROM vehicles v
            WHERE %s
            ORDER BY %s
            LIMIT :limit OFFSET :offset
            SQL, self::PREFIXED_COLUMNS, $where, $this->orderBy($filters)), $params));
    }

    public function countSearchPublic(VehicleFilters $filters): int
    {
        [$where, $params] = $this->criteria($filters, null, publicOnly: true);

        return (int) $this->connection->execute(
            sprintf('SELECT COUNT(*) FROM vehicles v WHERE %s', $where),
            $params,
        )->fetchColumn();
    }

    public function availableFilters(?string $ownerUserId): array
    {
        [$where, $params] = $this->criteria(new VehicleFilters(), $ownerUserId);

        return $this->facets($where, $params);
    }

    public function availableFiltersPublic(): array
    {
        [$where, $params] = $this->criteria(new VehicleFilters(), null, publicOnly: true);

        return $this->facets($where, $params);
    }

    /**
     * @param array<string, string|int|null> $params
     * @return array{brands: list<string>, models: list<string>, years: list<int>, transmissions: list<string>, body_types: list<string>, fuel_types: list<string>}
     */
    private function facets(string $where, array $params): array
    {
        $row = $this->connection->execute(sprintf(<<<'SQL'
            SELECT array_to_string(array_agg(DISTINCT v.brand ORDER BY v.brand), ',') AS brands,
                   array_to_string(array_agg(DISTINCT v.model ORDER BY v.model), ',') AS models,
                   array_to_string(array_agg(DISTINCT v.model_year ORDER BY v.model_year DESC) FILTER (WHERE v.model_year IS NOT NULL), ',') AS years,
                   array_to_string(array_agg(DISTINCT v.transmission) FILTER (WHERE v.transmission IS NOT NULL), ',') AS transmissions,
                   array_to_string(array_agg(DISTINCT v.body_type) FILTER (WHERE v.body_type IS NOT NULL), ',') AS body_types,
                   array_to_string(array_agg(DISTINCT v.fuel_type) FILTER (WHERE v.fuel_type IS NOT NULL), ',') AS fuel_types
            FROM vehicles v
            WHERE %s
            SQL, $where), $params)->fetch();

        if ($row === false) {
            return ['brands' => [], 'models' => [], 'years' => [], 'transmissions' => [], 'body_types' => [], 'fuel_types' => []];
        }

        $values = Row::from($row);

        return [
            'brands' => $this->split($values->nullableString('brands')),
            'models' => $this->split($values->nullableString('models')),
            'years' => array_map(intval(...), $this->split($values->nullableString('years'))),
            'transmissions' => $this->split($values->nullableString('transmissions')),
            'body_types' => $this->split($values->nullableString('body_types')),
            'fuel_types' => $this->split($values->nullableString('fuel_types')),
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
    private function criteria(VehicleFilters $filters, ?string $ownerUserId, bool $publicOnly = false): array
    {
        $conditions = ["v.status <> 'deleted'"];
        $params = [];

        if ($ownerUserId !== null) {
            $conditions[] = 'EXISTS (SELECT 1 FROM dealerships d WHERE d.id = v.dealership_id AND d.owner_user_id = :owner_user_id::uuid)';
            $params['owner_user_id'] = $ownerUserId;
        }

        // Explícito mesmo com RLS por trás: o banco reforça o que a aplicação já valida, nunca é a única linha de defesa.
        if ($publicOnly) {
            $conditions[] = "v.status = 'active'";
            $conditions[] = "EXISTS (SELECT 1 FROM dealerships d WHERE d.id = v.dealership_id AND d.status = 'active')";
        }

        // `websearch_to_tsquery` no termo cru evita erro de sintaxe (`&`/`|`/`!` viraria 500). `%` tolera
        // digitação errada; `to_tsquery(...:*)` é aditivo, acha prefixo de 1+ char (lexema inteiro não acha).
        if ($filters->term !== null) {
            $clauses = [
                "v.search_vector @@ websearch_to_tsquery('simple', :term)",
                sprintf('(%s) %% :term', self::SEARCHABLE_NAME),
                "v.search_vector @@ to_tsquery('simple', regexp_replace(websearch_to_tsquery('simple', :term)::text, '(\\S+)$', '\\1:*'))",
            ];

            // Termo curto demais pra `pg_trgm` extrair um trigrama útil -- o prefixo acima já cobre esse caso.
            if (mb_strlen(trim($filters->term)) >= 3) {
                $clauses[] = sprintf("(%s) ILIKE '%%' || :term || '%%'", self::SEARCHABLE_FULL_TEXT);
            }

            $conditions[] = '(' . implode(' OR ', $clauses) . ')';
            $params['term'] = $filters->term;
        }

        foreach (['brand' => $filters->brand, 'model' => $filters->model] as $field => $value) {
            if ($value !== null) {
                $conditions[] = sprintf("v.search_vector @@ websearch_to_tsquery('simple', :%s)", $field);
                $params[$field] = $value;
            }
        }

        $ranges = [
            'year_min' => ['v.model_year >= :year_min', $filters->yearMin],
            'year_max' => ['v.model_year <= :year_max', $filters->yearMax],
            'price_min' => ['v.price >= :price_min::numeric', $filters->priceMin?->toDecimal()],
            'price_max' => ['v.price <= :price_max::numeric', $filters->priceMax?->toDecimal()],
            'dealership_id' => ['v.dealership_id = :dealership_id::uuid', $filters->dealershipId],
            'transmission' => ['v.transmission = :transmission', $filters->transmission?->value],
            'body_type' => ['v.body_type = :body_type', $filters->bodyType?->value],
            'fuel_type' => ['v.fuel_type = :fuel_type', $filters->fuelType?->value],
            'mileage_km_max' => ['v.mileage_km <= :mileage_km_max', $filters->mileageKmMax],
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
                "ts_rank(v.search_vector, websearch_to_tsquery('simple', :term)) + similarity(%s, :term)"
                . " + ts_rank(v.search_vector, to_tsquery('simple', regexp_replace(websearch_to_tsquery('simple', :term)::text, '(\\S+)$', '\\1:*')))",
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

    /** `sort` explícito substitui a relevância inteira -- os dois juntos não fariam sentido pro usuário escolher. */
    private function orderBy(VehicleFilters $filters): string
    {
        return match ($filters->sort) {
            VehicleSort::PriceDesc => 'v.price DESC, v.id DESC',
            VehicleSort::PriceAsc => 'v.price ASC, v.id ASC',
            VehicleSort::YearDesc => 'v.model_year DESC NULLS LAST, v.id DESC',
            VehicleSort::CreatedDesc => 'v.created_at DESC, v.id DESC',
            VehicleSort::CreatedAsc => 'v.created_at ASC, v.id ASC',
            null => $this->relevance($filters) . 'v.created_at DESC, v.id DESC',
        };
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
            manufactureYear: $row->nullableInt('manufacture_year'),
            modelYear: $row->nullableInt('model_year'),
            // `numeric` sai do PDO como string e é assim que fica: converter pra float perderia centavo.
            price: Money::fromDecimal($row->string('price')),
            description: $row->nullableString('description'),
            mileageKm: $row->nullableInt('mileage_km'),
            transmission: $row->nullableEnum(Transmission::class, 'transmission'),
            bodyType: $row->nullableEnum(BodyType::class, 'body_type'),
            fuelType: $row->nullableEnum(FuelType::class, 'fuel_type'),
            color: $row->nullableString('color'),
            plateEndDigit: $row->nullableInt('plate_end_digit'),
            acceptsTrade: $row->bool('accepts_trade'),
            ipvaPaid: $row->bool('ipva_paid'),
            licensed: $row->bool('licensed'),
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
            'manufacture_year' => $vehicle->manufactureYear,
            'model_year' => $vehicle->modelYear,
            'price' => $vehicle->price->toDecimal(),
            'description' => $vehicle->description,
            'mileage_km' => $vehicle->mileageKm,
            'transmission' => $vehicle->transmission?->value,
            'body_type' => $vehicle->bodyType?->value,
            'fuel_type' => $vehicle->fuelType?->value,
            'color' => $vehicle->color,
            'plate_end_digit' => $vehicle->plateEndDigit,
            'accepts_trade' => $vehicle->acceptsTrade,
            'ipva_paid' => $vehicle->ipvaPaid,
            'licensed' => $vehicle->licensed,
            'status' => $vehicle->trash->status->value,
            'trashed_by_dealership_trash' => $vehicle->trashedByDealershipTrash,
            'trashed_at' => $vehicle->trash->trashedAt?->format(DATE_ATOM),
            'anonymized_at' => $vehicle->trash->anonymizedAt?->format(DATE_ATOM),
            'created_at' => $vehicle->createdAt->format(DATE_ATOM),
            'updated_at' => $vehicle->updatedAt->format(DATE_ATOM),
        ];
    }
}
