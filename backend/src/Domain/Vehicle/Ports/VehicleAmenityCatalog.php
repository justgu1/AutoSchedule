<?php

declare(strict_types=1);

namespace App\Domain\Vehicle\Ports;

use App\Domain\Vehicle\Amenity;

interface VehicleAmenityCatalog
{
    /** @return list<Amenity> */
    public function all(): array;

    /** Valida ids recebidos no payload -- 422 e não erro de FK quando o front manda um id que não existe.
     * @param list<string> $ids
     * @return list<string> só os que de fato existem no catálogo
     */
    public function existingIds(array $ids): array;
}
