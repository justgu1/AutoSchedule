<?php

declare(strict_types=1);

namespace App\Domain\Vehicle\Ports;

use App\Domain\Vehicle\VehicleImage;

interface VehicleImageRepository
{
    /** @return list<VehicleImage> ordenadas por `position` */
    public function findByVehicle(string $vehicleId): array;

    public function findById(string $id): ?VehicleImage;

    /**
     * A capa de vários veículos numa consulta só -- a vitrine resolveria N+1 sem isto.
     *
     * @param list<string> $vehicleIds
     * @return array<string, VehicleImage> indexado por `vehicle_id`
     */
    public function findCoversFor(array $vehicleIds): array;

    public function insert(VehicleImage $image): void;

    public function delete(string $id): void;

    public function deleteAllForVehicle(string $vehicleId): void;

    /** Próxima posição livre da galeria; `0` quando ela está vazia. */
    public function nextPosition(string $vehicleId): int;

    /**
     * Reordena numa transação de duas fases, porque o UNIQUE `(vehicle_id, position)` é imediato:
     * a primeira passada move tudo pra uma faixa negativa disjunta, a segunda escreve a ordem final.
     *
     * @param list<string> $imageIdsInOrder
     */
    public function reorder(string $vehicleId, array $imageIdsInOrder): void;
}
