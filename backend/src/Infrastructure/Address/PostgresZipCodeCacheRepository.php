<?php

declare(strict_types=1);

namespace App\Infrastructure\Address;

use App\Domain\Address\Ports\ZipCodeCacheRepository;
use App\Domain\Address\ZipCodeAddress;
use App\Infrastructure\Database\DatabaseConnection;

final readonly class PostgresZipCodeCacheRepository implements ZipCodeCacheRepository
{
    public function __construct(private DatabaseConnection $connection)
    {
    }

    public function find(string $zipCode): ?ZipCodeAddress
    {
        $statement = $this->connection->pdo()->prepare(
            'SELECT street, neighborhood, city, state FROM zip_code_cache WHERE zip_code = ?',
        );
        $statement->execute([$zipCode]);
        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->fromRow($row);
    }

    /** @param array<string, mixed> $row */
    private function fromRow(array $row): ZipCodeAddress
    {
        return new ZipCodeAddress(
            street: $row['street'],
            neighborhood: $row['neighborhood'],
            city: $row['city'],
            state: $row['state'],
        );
    }

    /** `ON CONFLICT DO NOTHING` -- duas requests resolvendo o mesmo CEP novo ao mesmo tempo não é erro, só a segunda grava à toa. */
    public function save(string $zipCode, ZipCodeAddress $address): void
    {
        $statement = $this->connection->pdo()->prepare(<<<'SQL'
            INSERT INTO zip_code_cache (zip_code, street, neighborhood, city, state)
            VALUES (:zip_code, :street, :neighborhood, :city, :state)
            ON CONFLICT (zip_code) DO NOTHING
            SQL);

        $statement->execute([
            'zip_code' => $zipCode,
            'street' => $address->street,
            'neighborhood' => $address->neighborhood,
            'city' => $address->city,
            'state' => $address->state,
        ]);
    }
}
