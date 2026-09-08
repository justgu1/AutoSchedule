<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Persistence;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;

/**
 * A galeria não repete o predicado de dono: ela delega pro RLS do veículo. Estes casos provam
 * que a delegação vale de verdade, inclusive pro contexto de serviço, que é quem grava a foto.
 */
#[Group('integration')]
final class VehicleImageRlsPolicyTest extends TestCase
{
    private \PDO $admin;
    private \PDO $rls;
    private string $sellerId;
    private string $otherSellerId;
    private string $vehicleId;
    private string $otherVehicleId;
    private string $imageId;
    private string $otherImageId;

    protected function setUp(): void
    {
        $this->admin = TestDatabase::connect()->pdo();

        $this->sellerId = $this->insertSellerUser('rls-image-seller@example.com');
        $this->otherSellerId = $this->insertSellerUser('rls-image-other@example.com');
        $this->vehicleId = $this->insertVehicle($this->insertDealership($this->sellerId, 'RLS Image Center'));
        $this->otherVehicleId = $this->insertVehicle($this->insertDealership($this->otherSellerId, 'RLS Image Other'));
        $this->imageId = $this->insertImage($this->vehicleId);
        $this->otherImageId = $this->insertImage($this->otherVehicleId);

        $this->rls = TestDatabase::connectAsApp()->pdo();
    }

    protected function tearDown(): void
    {
        $this->admin->exec('DELETE FROM vehicle_images');
        $this->admin->prepare('DELETE FROM vehicles WHERE id IN (?, ?)')->execute([$this->vehicleId, $this->otherVehicleId]);
        $this->admin->prepare('DELETE FROM dealerships WHERE owner_user_id IN (?, ?)')->execute([$this->sellerId, $this->otherSellerId]);
        $this->admin->prepare('DELETE FROM users WHERE id IN (?, ?)')->execute([$this->sellerId, $this->otherSellerId]);
    }

    #[Test]
    public function imagem_herda_a_visibilidade_do_proprio_veiculo(): void
    {
        $this->rls->beginTransaction();
        $this->setContext($this->sellerId, 'seller');
        $ids = $this->queryImageIds();
        $this->rls->rollBack();

        $this->assertSame([$this->imageId], $ids);
    }

    #[Test]
    public function seller_nao_enxerga_imagem_de_veiculo_de_outro_seller(): void
    {
        $this->rls->beginTransaction();
        $this->setContext($this->sellerId, 'seller');
        $ids = $this->queryImageIds();
        $this->rls->rollBack();

        $this->assertNotContains($this->otherImageId, $ids);
    }

    #[Test]
    public function admin_enxerga_qualquer_imagem(): void
    {
        $this->rls->beginTransaction();
        $this->setContext($this->sellerId, 'admin');
        $ids = $this->queryImageIds();
        $this->rls->rollBack();

        $this->assertContains($this->imageId, $ids);
        $this->assertContains($this->otherImageId, $ids);
    }

    #[Test]
    public function sem_contexto_setado_nenhuma_linha_e_retornada(): void
    {
        $this->rls->beginTransaction();
        $ids = $this->queryImageIds();
        $this->rls->rollBack();

        $this->assertSame([], $ids);
    }

    /** Quem grava a foto é o worker, sem identidade -- sem isto a galeria assíncrona não funcionaria. */
    #[Test]
    public function contexto_de_servico_insere_e_enxerga_imagem(): void
    {
        $this->rls->beginTransaction();
        $this->rls->exec("SET LOCAL app.is_service_context = 'true'");
        $this->rls->prepare('INSERT INTO vehicle_images (vehicle_id, file_id, position) VALUES (?, ?, 1)')
            ->execute([$this->vehicleId, $this->insertFile()]);
        $ids = $this->queryImageIds();
        $this->rls->rollBack();

        $this->assertContains($this->imageId, $ids);
        $this->assertCount(3, $ids);
    }

    #[Test]
    public function leitura_publica_enxerga_a_galeria_de_veiculo_visivel(): void
    {
        $this->rls->beginTransaction();
        $this->rls->exec("SET LOCAL app.is_public_read = 'true'");
        $ids = $this->queryImageIds();
        $this->rls->rollBack();

        $this->assertContains($this->imageId, $ids);
    }

    private function setContext(string $userId, string $role): void
    {
        $this->rls->exec('SET LOCAL app.current_user_id = ' . $this->rls->quote($userId));
        $this->rls->exec('SET LOCAL app.current_user_role = ' . $this->rls->quote($role));
    }

    /** @return list<string> */
    private function queryImageIds(): array
    {
        $statement = $this->rls->query('SELECT id FROM vehicle_images');
        $ids = [];

        foreach ($statement === false ? [] : $statement->fetchAll() as $row) {
            \assert(is_array($row) && is_string($row['id']));
            $ids[] = $row['id'];
        }

        return $ids;
    }

    private function insertSellerUser(string $email): string
    {
        $statement = $this->admin->prepare(<<<'SQL'
            INSERT INTO users (name, email, password, role)
            VALUES ('Seller RLS', :email, 'hash', 'seller')
            RETURNING id
            SQL);
        $statement->execute(['email' => $email]);

        return (string) $statement->fetchColumn();
    }

    private function insertDealership(string $ownerUserId, string $name): string
    {
        $statement = $this->admin->prepare(<<<'SQL'
            INSERT INTO dealerships (owner_user_id, name, slug, zip_code, address, number, neighborhood, city, state)
            VALUES (:owner_user_id, :name, :slug, '00000-000', 'Rua', '1', 'Bairro', 'Cidade', 'SP')
            RETURNING id
            SQL);
        $statement->execute([
            'owner_user_id' => $ownerUserId,
            'name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)) . '-' . bin2hex(random_bytes(3)),
        ]);

        return (string) $statement->fetchColumn();
    }

    private function insertVehicle(string $dealershipId): string
    {
        $statement = $this->admin->prepare(<<<'SQL'
            INSERT INTO vehicles (dealership_id, brand, model, price)
            VALUES (:dealership_id, 'Chevrolet', 'Onix', 89900.00)
            RETURNING id
            SQL);
        $statement->execute(['dealership_id' => $dealershipId]);

        return (string) $statement->fetchColumn();
    }

    private function insertImage(string $vehicleId): string
    {
        $statement = $this->admin->prepare(<<<'SQL'
            INSERT INTO vehicle_images (vehicle_id, file_id, position)
            VALUES (:vehicle_id, :file_id, 0)
            RETURNING id
            SQL);
        $statement->execute(['vehicle_id' => $vehicleId, 'file_id' => $this->insertFile()]);

        return (string) $statement->fetchColumn();
    }

    private function insertFile(): string
    {
        $checksum = bin2hex(random_bytes(16));
        $statement = $this->admin->prepare(<<<'SQL'
            INSERT INTO files (path, original_name, mime_type, size_bytes, checksum)
            VALUES (:path, 'foto.webp', 'image/webp', 123, :checksum)
            RETURNING id
            SQL);
        $statement->execute(['path' => $checksum, 'checksum' => $checksum]);

        return (string) $statement->fetchColumn();
    }
}
