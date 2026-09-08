<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Persistence;

use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Domain\Vehicle\VehicleImage;
use App\Infrastructure\Persistence\PostgresVehicleImageRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;

#[Group('integration')]
final class PostgresVehicleImageRepositoryTest extends TestCase
{
    private \PDO $pdo;
    private PostgresVehicleImageRepository $repository;
    private string $vehicleId;

    protected function setUp(): void
    {
        $connection = TestDatabase::connect();
        $this->pdo = $connection->pdo();

        $this->pdo->beginTransaction();
        $this->repository = new PostgresVehicleImageRepository($connection);
        $this->vehicleId = $this->insertVehicle($this->insertDealership($this->insertSellerUser()));
    }

    protected function tearDown(): void
    {
        $this->pdo->rollBack();
    }

    #[Test]
    public function find_by_vehicle_devolve_ordenado_por_posicao(): void
    {
        $second = $this->attach(1);
        $first = $this->attach(0);

        $found = $this->repository->findByVehicle($this->vehicleId);

        $this->assertSame([$first->id, $second->id], array_map(static fn (VehicleImage $i): string => $i->id, $found));
    }

    #[Test]
    public function posicao_duplicada_no_mesmo_veiculo_vira_conflito_de_dominio(): void
    {
        $this->attach(0);

        try {
            $this->attach(0);
            $this->fail('Expected a conflict.');
        } catch (DomainException $exception) {
            $this->assertSame(DomainErrorType::Conflict, $exception->type());
        }
    }

    #[Test]
    public function next_position_comeca_em_zero_e_segue_o_fim_da_galeria(): void
    {
        $this->assertSame(0, $this->repository->nextPosition($this->vehicleId));

        $this->attach(0);
        $this->attach(1);

        $this->assertSame(2, $this->repository->nextPosition($this->vehicleId));
    }

    /** A faixa negativa da primeira passada é o que evita violar o UNIQUE no meio da reordenação. */
    #[Test]
    public function reorder_troca_as_posicoes_sem_violar_o_unique(): void
    {
        $first = $this->attach(0);
        $second = $this->attach(1);
        $third = $this->attach(2);

        $this->repository->reorder($this->vehicleId, [$third->id, $first->id, $second->id]);

        $found = $this->repository->findByVehicle($this->vehicleId);
        $this->assertSame([$third->id, $first->id, $second->id], array_map(static fn (VehicleImage $i): string => $i->id, $found));
        $this->assertSame([0, 1, 2], array_map(static fn (VehicleImage $i): int => $i->position, $found));
    }

    #[Test]
    public function find_covers_for_traz_a_posicao_zero_de_varios_veiculos_numa_consulta_so(): void
    {
        $cover = $this->attach(0);
        $this->attach(1);
        $otherVehicleId = $this->insertVehicle($this->insertDealership($this->insertSellerUser()));
        $otherCover = $this->insertImage($otherVehicleId, 0);

        $covers = $this->repository->findCoversFor([$this->vehicleId, $otherVehicleId]);

        $this->assertSame($cover->id, $covers[$this->vehicleId]->id);
        $this->assertSame($otherCover->id, $covers[$otherVehicleId]->id);
    }

    #[Test]
    public function delete_all_for_vehicle_esvazia_so_a_galeria_daquele_veiculo(): void
    {
        $this->attach(0);
        $otherVehicleId = $this->insertVehicle($this->insertDealership($this->insertSellerUser()));
        $this->insertImage($otherVehicleId, 0);

        $this->repository->deleteAllForVehicle($this->vehicleId);

        $this->assertSame([], $this->repository->findByVehicle($this->vehicleId));
        $this->assertCount(1, $this->repository->findByVehicle($otherVehicleId));
    }

    private function attach(int $position): VehicleImage
    {
        return $this->insertImage($this->vehicleId, $position);
    }

    private function insertImage(string $vehicleId, int $position): VehicleImage
    {
        $image = VehicleImage::register($vehicleId, $this->insertFile(), $position);
        $this->repository->insert($image);

        return $image;
    }

    private function insertFile(): string
    {
        $checksum = bin2hex(random_bytes(16));
        $statement = $this->pdo->prepare(<<<'SQL'
            INSERT INTO files (path, original_name, mime_type, size_bytes, checksum)
            VALUES (:path, 'foto.webp', 'image/webp', 123, :checksum)
            RETURNING id
            SQL);
        $statement->execute(['path' => $checksum, 'checksum' => $checksum]);

        return (string) $statement->fetchColumn();
    }

    private function insertSellerUser(): string
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            INSERT INTO users (name, email, password, role)
            VALUES ('Seller Test', :email, 'hash', 'seller')
            RETURNING id
            SQL);
        $statement->execute(['email' => 'seller-' . bin2hex(random_bytes(8)) . '@example.com']);

        return (string) $statement->fetchColumn();
    }

    private function insertDealership(string $ownerUserId): string
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            INSERT INTO dealerships (owner_user_id, name, slug, zip_code, address, number, neighborhood, city, state)
            VALUES (:owner_user_id, 'Auto Center', :slug, '01000-000', 'Rua Antiga', '10', 'Bairro', 'Cidade', 'SP')
            RETURNING id
            SQL);
        $statement->execute(['owner_user_id' => $ownerUserId, 'slug' => 'auto-center-' . bin2hex(random_bytes(4))]);

        return (string) $statement->fetchColumn();
    }

    private function insertVehicle(string $dealershipId): string
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            INSERT INTO vehicles (dealership_id, brand, model, price)
            VALUES (:dealership_id, 'Chevrolet', 'Onix', 89900.00)
            RETURNING id
            SQL);
        $statement->execute(['dealership_id' => $dealershipId]);

        return (string) $statement->fetchColumn();
    }
}
