<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Vehicle\Ports\VehicleImageRepository;
use App\Domain\Vehicle\VehicleImage;

final class InMemoryVehicleImageRepository implements VehicleImageRepository
{
    /** @var array<string, VehicleImage> */
    private array $images = [];

    public function findByVehicle(string $vehicleId): array
    {
        $ofVehicle = array_values(array_filter($this->images, static fn (VehicleImage $i): bool => $i->vehicleId === $vehicleId));
        usort($ofVehicle, static fn (VehicleImage $a, VehicleImage $b): int => $a->position <=> $b->position);

        return $ofVehicle;
    }

    public function findById(string $id): ?VehicleImage
    {
        return $this->images[$id] ?? null;
    }

    public function findCoversFor(array $vehicleIds): array
    {
        $covers = [];

        foreach ($this->images as $image) {
            if ($image->position === 0 && in_array($image->vehicleId, $vehicleIds, true)) {
                $covers[$image->vehicleId] = $image;
            }
        }

        return $covers;
    }

    public function insert(VehicleImage $image): void
    {
        $this->images[$image->id] = $image;
    }

    public function delete(string $id): void
    {
        unset($this->images[$id]);
    }

    public function deleteAllForVehicle(string $vehicleId): void
    {
        foreach ($this->findByVehicle($vehicleId) as $image) {
            $this->delete($image->id);
        }
    }

    public function nextPosition(string $vehicleId): int
    {
        $positions = array_map(static fn (VehicleImage $i): int => $i->position, $this->findByVehicle($vehicleId));

        return $positions === [] ? 0 : max($positions) + 1;
    }

    public function reorder(string $vehicleId, array $imageIdsInOrder): void
    {
        foreach ($imageIdsInOrder as $position => $imageId) {
            $image = $this->findById($imageId);

            if ($image instanceof VehicleImage && $image->vehicleId === $vehicleId) {
                $this->images[$imageId] = $image->movedTo($position);
            }
        }
    }
}
