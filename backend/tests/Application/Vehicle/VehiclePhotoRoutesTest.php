<?php

declare(strict_types=1);

namespace Tests\Application\Vehicle;

use App\Application\Shared\ActorContext;
use App\Application\Vehicle\PurgeVehicle;
use App\Application\Vehicle\RemoveVehiclePhoto;
use App\Application\Vehicle\ReorderVehiclePhotos;
use App\Application\Vehicle\VehicleFinder;
use App\Application\Vehicle\VehicleGallery;
use App\Domain\Audit\AuditEvent;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Domain\File\StoredFile;
use App\Domain\Shared\Money;
use App\Domain\User\UserRole;
use App\Domain\Vehicle\Vehicle;
use App\Domain\Vehicle\VehicleImage;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\DirectTransaction;
use Tests\Support\FakeAuditLogger;
use Tests\Support\InMemoryFileRepository;
use Tests\Support\InMemoryVehicleImageRepository;
use Tests\Support\InMemoryVehicleRepository;

final class VehiclePhotoRoutesTest extends TestCase
{
    private InMemoryVehicleRepository $vehicles;
    private InMemoryVehicleImageRepository $images;
    private InMemoryFileRepository $files;
    private FakeAuditLogger $audit;
    private VehicleGallery $gallery;
    private Vehicle $vehicle;

    protected function setUp(): void
    {
        $this->vehicles = new InMemoryVehicleRepository();
        $this->images = new InMemoryVehicleImageRepository();
        $this->files = new InMemoryFileRepository();
        $this->audit = new FakeAuditLogger();
        $this->gallery = new VehicleGallery($this->images, $this->files, new RecordingStorageProvider());

        $this->vehicle = Vehicle::register('dealership-1', 'Chevrolet', 'Onix', null, 2023, new Money(8990000));
        $this->vehicles->insert($this->vehicle);
    }

    #[Test]
    public function remover_imagem_de_outro_veiculo_e_404(): void
    {
        $foreign = $this->attach('vehicle-alheio', 0);

        try {
            $this->removeVehiclePhoto()($this->vehicle->id, $foreign->id, $this->actor());
            $this->fail('Expected a not found error.');
        } catch (DomainException $exception) {
            $this->assertSame(DomainErrorType::NotFound, $exception->type());
        }

        $this->assertNotNull($this->images->findById($foreign->id));
    }

    #[Test]
    public function remover_imagem_solta_a_linha_e_audita(): void
    {
        $image = $this->attach($this->vehicle->id, 0);

        $this->removeVehiclePhoto()($this->vehicle->id, $image->id, $this->actor());

        $this->assertNull($this->images->findById($image->id));
        $this->assertSame([AuditEvent::VehicleImageRemoved], $this->audit->events);
    }

    #[Test]
    public function reordenar_reescreve_as_posicoes_na_ordem_pedida(): void
    {
        $first = $this->attach($this->vehicle->id, 0);
        $second = $this->attach($this->vehicle->id, 1);
        $third = $this->attach($this->vehicle->id, 2);

        $this->reorderVehiclePhotos()($this->vehicle->id, [$third->id, $first->id, $second->id], $this->actor());

        $ids = array_map(static fn (VehicleImage $i): string => $i->id, $this->images->findByVehicle($this->vehicle->id));
        $this->assertSame([$third->id, $first->id, $second->id], $ids);
        $this->assertSame([AuditEvent::VehicleImagesReordered], $this->audit->events);
    }

    /** Ordem parcial converge pra estado errado sem ninguém perceber, então ela é recusada inteira. */
    #[Test]
    public function reordenar_recusa_ordem_que_nao_lista_a_galeria_inteira(): void
    {
        $first = $this->attach($this->vehicle->id, 0);
        $this->attach($this->vehicle->id, 1);

        try {
            $this->reorderVehiclePhotos()($this->vehicle->id, [$first->id], $this->actor());
            $this->fail('Expected a validation error.');
        } catch (DomainException $exception) {
            $this->assertSame(DomainErrorType::Validation, $exception->type());
        }

        $this->assertSame([], $this->audit->events);
    }

    /** A rotina agendada não alcança storage, então a purga manual é quem limpa a galeria. */
    #[Test]
    public function purge_limpa_a_galeria_junto(): void
    {
        $this->attach($this->vehicle->id, 0);
        $this->attach($this->vehicle->id, 1);
        $this->vehicles->trash($this->vehicle->id);

        $this->purgeVehicle()($this->vehicle->id, $this->actor());

        $this->assertSame([], $this->images->findByVehicle($this->vehicle->id));
        $this->assertSame([AuditEvent::VehiclePurged], $this->audit->events);
    }

    #[Test]
    public function purge_recusa_veiculo_que_nao_esta_na_lixeira(): void
    {
        try {
            $this->purgeVehicle()($this->vehicle->id, $this->actor());
            $this->fail('Expected a conflict.');
        } catch (DomainException $exception) {
            $this->assertSame(DomainErrorType::Conflict, $exception->type());
        }
    }

    private function attach(string $vehicleId, int $position): VehicleImage
    {
        $checksum = bin2hex(random_bytes(8));
        $file = StoredFile::register($checksum, 'foto.webp', 'image/webp', 100, $checksum, null);
        $this->files->insert($file);
        $image = VehicleImage::register($vehicleId, $file->id, $position);
        $this->images->insert($image);

        return $image;
    }

    private function removeVehiclePhoto(): RemoveVehiclePhoto
    {
        return new RemoveVehiclePhoto(
            new VehicleFinder($this->vehicles),
            $this->images,
            $this->gallery,
            $this->audit,
            new DirectTransaction(),
        );
    }

    private function reorderVehiclePhotos(): ReorderVehiclePhotos
    {
        return new ReorderVehiclePhotos(new VehicleFinder($this->vehicles), $this->images, $this->audit, new DirectTransaction());
    }

    private function purgeVehicle(): PurgeVehicle
    {
        return new PurgeVehicle(
            new VehicleFinder($this->vehicles),
            $this->vehicles,
            $this->gallery,
            $this->audit,
            new DirectTransaction(),
        );
    }

    private function actor(): ActorContext
    {
        return new ActorContext('seller-1', UserRole::Seller);
    }
}
