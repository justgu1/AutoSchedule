<?php

declare(strict_types=1);

namespace Tests\Application\Vehicle;

use App\Application\Vehicle\VehicleGallery;
use App\Domain\File\Ports\StorageProvider;
use App\Domain\File\StoredFile;
use App\Domain\Vehicle\VehicleImage;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\InMemoryFileRepository;
use Tests\Support\InMemoryVehicleImageRepository;

final class VehicleGalleryTest extends TestCase
{
    private InMemoryVehicleImageRepository $images;
    private InMemoryFileRepository $files;
    private RecordingStorageProvider $storage;
    private VehicleGallery $gallery;

    protected function setUp(): void
    {
        $this->images = new InMemoryVehicleImageRepository();
        $this->files = new InMemoryFileRepository();
        $this->storage = new RecordingStorageProvider();
        $this->gallery = new VehicleGallery($this->images, $this->files, $this->storage);
    }

    #[Test]
    public function galeria_vem_ordenada_por_posicao_com_a_url_de_cada_imagem(): void
    {
        $second = $this->attach('vehicle-1', 'checksum-b', 1);
        $first = $this->attach('vehicle-1', 'checksum-a', 0);

        $gallery = $this->gallery->forVehicle('vehicle-1');

        $this->assertSame([$first->id, $second->id], array_column($gallery, 'id'));
        $this->assertSame('https://storage.test/checksum-a', $gallery[0]['url']);
    }

    #[Test]
    public function capas_de_varios_veiculos_saem_indexadas_por_veiculo(): void
    {
        $this->attach('vehicle-1', 'checksum-a', 0);
        $this->attach('vehicle-1', 'checksum-b', 1);
        $this->attach('vehicle-2', 'checksum-c', 0);

        $covers = $this->gallery->coversFor(['vehicle-1', 'vehicle-2']);

        $this->assertSame([
            'vehicle-1' => 'https://storage.test/checksum-a',
            'vehicle-2' => 'https://storage.test/checksum-c',
        ], $covers);
    }

    #[Test]
    public function remover_imagem_apaga_o_arquivo_quando_ninguem_mais_o_referencia(): void
    {
        $image = $this->attach('vehicle-1', 'checksum-a', 0);

        $this->gallery->detach($image);

        $this->assertSame([], $this->gallery->forVehicle('vehicle-1'));
        $this->assertSame(['checksum-a'], $this->storage->deleted);
    }

    /** Dedupe por checksum faz dois anúncios com a mesma foto compartilharem o arquivo. */
    #[Test]
    public function remover_imagem_preserva_o_arquivo_quando_outro_veiculo_ainda_o_usa(): void
    {
        $image = $this->attach('vehicle-1', 'checksum-a', 0);
        $this->files->referenceCountIs($image->fileId, 1);

        $this->gallery->detach($image);

        $this->assertSame([], $this->storage->deleted);
    }

    #[Test]
    public function detach_all_esvazia_a_galeria_do_veiculo(): void
    {
        $this->attach('vehicle-1', 'checksum-a', 0);
        $this->attach('vehicle-1', 'checksum-b', 1);
        $this->attach('vehicle-2', 'checksum-c', 0);

        $this->gallery->detachAll('vehicle-1');

        $this->assertSame([], $this->gallery->forVehicle('vehicle-1'));
        $this->assertCount(1, $this->gallery->forVehicle('vehicle-2'));
    }

    private function attach(string $vehicleId, string $checksum, int $position): VehicleImage
    {
        $file = StoredFile::register($checksum, 'foto.webp', 'image/webp', 100, $checksum, null);
        $this->files->insert($file);
        $image = VehicleImage::register($vehicleId, $file->id, $position);
        $this->images->insert($image);

        return $image;
    }
}

final class RecordingStorageProvider implements StorageProvider
{
    /** @var list<string> */
    public array $deleted = [];

    public function put(string $path, string $contents, string $mimeType): void
    {
    }

    public function url(string $path): string
    {
        return 'https://storage.test/' . $path;
    }

    public function delete(string $path): void
    {
        $this->deleted[] = $path;
    }
}
