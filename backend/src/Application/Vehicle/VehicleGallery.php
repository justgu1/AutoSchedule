<?php

declare(strict_types=1);

namespace App\Application\Vehicle;

use App\Domain\File\Ports\FileRepository;
use App\Domain\File\Ports\StorageProvider;
use App\Domain\File\StoredFile;
use App\Domain\Vehicle\Ports\VehicleImageRepository;
use App\Domain\Vehicle\VehicleImage;

final readonly class VehicleGallery
{
    public function __construct(
        private VehicleImageRepository $images,
        private FileRepository $files,
        private StorageProvider $storage,
    ) {
    }

    /** @return list<array{id: string, position: int, url: string}> */
    public function forVehicle(string $vehicleId): array
    {
        $gallery = [];

        foreach ($this->images->findByVehicle($vehicleId) as $image) {
            $url = $this->urlOf($image);

            if ($url !== null) {
                $gallery[] = ['id' => $image->id, 'position' => $image->position, 'url' => $url];
            }
        }

        return $gallery;
    }

    /**
     * @param list<string> $vehicleIds
     * @return array<string, string> `vehicle_id` => URL da capa
     */
    public function coversFor(array $vehicleIds): array
    {
        $covers = [];

        foreach ($this->images->findCoversFor($vehicleIds) as $vehicleId => $image) {
            $url = $this->urlOf($image);

            if ($url !== null) {
                $covers[$vehicleId] = $url;
            }
        }

        return $covers;
    }

    /** Solta a linha da galeria e o arquivo junto, se mais ninguém apontar pra ele. */
    public function detach(VehicleImage $image): void
    {
        $this->images->delete($image->id);
        $path = $this->files->deleteIfUnreferenced($image->fileId);

        if ($path !== null) {
            $this->storage->delete($path);
        }
    }

    public function detachAll(string $vehicleId): void
    {
        foreach ($this->images->findByVehicle($vehicleId) as $image) {
            $this->detach($image);
        }
    }

    private function urlOf(VehicleImage $image): ?string
    {
        $file = $this->files->findById($image->fileId);

        return $file instanceof StoredFile ? $this->storage->url($file->path) : null;
    }
}
