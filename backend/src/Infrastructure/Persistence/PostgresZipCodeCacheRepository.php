<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\ZipCode\Ports\ZipCodeCacheRepository;
use App\Domain\ZipCode\ZipCodeAddress;

final readonly class PostgresZipCodeCacheRepository implements ZipCodeCacheRepository
{
    public function __construct(private DatabaseConnection $connection)
    {
    }

    public function find(string $zipCode): ?ZipCodeAddress
    {
        $statement = $this->connection->execute(
            'SELECT street, neighborhood, city, state FROM zip_code_cache WHERE zip_code = :zip_code',
            ['zip_code' => $zipCode],
        );
        $row = $statement->fetch();

        return $row === false ? null : $this->fromRow(Row::from($row));
    }

    private function fromRow(Row $row): ZipCodeAddress
    {
        return new ZipCodeAddress(
            street: $row->string('street'),
            neighborhood: $row->string('neighborhood'),
            city: $row->string('city'),
            state: $row->string('state'),
        );
    }

    /** `ON CONFLICT DO NOTHING` -- duas requests resolvendo o mesmo CEP novo ao mesmo tempo não é erro, só a segunda grava à toa. */
    public function save(string $zipCode, ZipCodeAddress $address): void
    {
        $this->connection->execute(<<<'SQL'
            INSERT INTO zip_code_cache (zip_code, street, neighborhood, city, state)
            VALUES (:zip_code, :street, :neighborhood, :city, :state)
            ON CONFLICT (zip_code) DO NOTHING
            SQL, [
            'zip_code' => $zipCode,
            'street' => $address->street,
            'neighborhood' => $address->neighborhood,
            'city' => $address->city,
            'state' => $address->state,
        ]);
    }
}
