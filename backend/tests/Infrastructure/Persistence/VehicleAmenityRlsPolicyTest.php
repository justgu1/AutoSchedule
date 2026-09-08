<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Persistence;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;

/**
 * Catálogo é global e só-leitura (curadoria via seeder admin); os vínculos delegam pro RLS do
 * veículo, mesmo raciocínio de `vehicle_images` -- `VehicleImageRlsPolicyTest` é o espelho disto.
 */
#[Group('integration')]
final class VehicleAmenityRlsPolicyTest extends TestCase
{
    private \PDO $admin;
    private \PDO $rls;
    private string $sellerId;
    private string $otherSellerId;
    private string $vehicleId;
    private string $otherVehicleId;
    private string $amenityId;

    protected function setUp(): void
    {
        $this->admin = TestDatabase::connect()->pdo();

        $this->sellerId = $this->insertSellerUser('rls-amenity-seller@example.com');
        $this->otherSellerId = $this->insertSellerUser('rls-amenity-other@example.com');
        $this->vehicleId = $this->insertVehicle($this->insertDealership($this->sellerId, 'RLS Amenity Center'));
        $this->otherVehicleId = $this->insertVehicle($this->insertDealership($this->otherSellerId, 'RLS Amenity Other'));
        $this->amenityId = $this->insertAmenity();
        $this->insertLink($this->vehicleId, $this->amenityId);
        $this->insertLink($this->otherVehicleId, $this->amenityId);

        $this->rls = TestDatabase::connectAsApp()->pdo();
    }

    protected function tearDown(): void
    {
        $this->admin->exec('DELETE FROM vehicle_amenity_links');
        $this->admin->prepare('DELETE FROM vehicle_amenity_catalog WHERE id = ?')->execute([$this->amenityId]);
        $this->admin->prepare('DELETE FROM vehicles WHERE id IN (?, ?)')->execute([$this->vehicleId, $this->otherVehicleId]);
        $this->admin->prepare('DELETE FROM dealerships WHERE owner_user_id IN (?, ?)')->execute([$this->sellerId, $this->otherSellerId]);
        $this->admin->prepare('DELETE FROM users WHERE id IN (?, ?)')->execute([$this->sellerId, $this->otherSellerId]);
    }

    #[Test]
    public function catalogo_e_visivel_mesmo_sem_contexto_nenhum(): void
    {
        $this->rls->beginTransaction();
        $ids = $this->queryCatalogIds();
        $this->rls->rollBack();

        $this->assertContains($this->amenityId, $ids);
    }

    #[Test]
    public function seller_nao_consegue_inserir_no_catalogo(): void
    {
        $this->rls->beginTransaction();
        $this->setContext($this->sellerId, 'seller');

        try {
            $this->expectException(\PDOException::class);
            $this->rls->prepare("INSERT INTO vehicle_amenity_catalog (code, label, position) VALUES ('teste-rls', 'Teste RLS', 99)")->execute();
        } finally {
            $this->rls->rollBack();
        }
    }

    #[Test]
    public function admin_consegue_inserir_no_catalogo(): void
    {
        $this->rls->beginTransaction();
        $this->setContext($this->sellerId, 'admin');
        $this->rls->prepare("INSERT INTO vehicle_amenity_catalog (code, label, position) VALUES ('teste-rls-admin', 'Teste RLS Admin', 99)")->execute();
        $ids = $this->queryCatalogIds();
        $this->rls->rollBack();

        $this->assertGreaterThan(1, count($ids));
    }

    #[Test]
    public function vinculo_herda_a_visibilidade_do_proprio_veiculo(): void
    {
        $this->rls->beginTransaction();
        $this->setContext($this->sellerId, 'seller');
        $vehicleIds = $this->queryLinkedVehicleIds();
        $this->rls->rollBack();

        $this->assertSame([$this->vehicleId], $vehicleIds);
    }

    #[Test]
    public function seller_nao_enxerga_vinculo_de_veiculo_de_outro_seller(): void
    {
        $this->rls->beginTransaction();
        $this->setContext($this->sellerId, 'seller');
        $vehicleIds = $this->queryLinkedVehicleIds();
        $this->rls->rollBack();

        $this->assertNotContains($this->otherVehicleId, $vehicleIds);
    }

    #[Test]
    public function admin_enxerga_qualquer_vinculo(): void
    {
        $this->rls->beginTransaction();
        $this->setContext($this->sellerId, 'admin');
        $vehicleIds = $this->queryLinkedVehicleIds();
        $this->rls->rollBack();

        $this->assertContains($this->vehicleId, $vehicleIds);
        $this->assertContains($this->otherVehicleId, $vehicleIds);
    }

    #[Test]
    public function sem_contexto_setado_nenhum_vinculo_e_retornado(): void
    {
        $this->rls->beginTransaction();
        $vehicleIds = $this->queryLinkedVehicleIds();
        $this->rls->rollBack();

        $this->assertSame([], $vehicleIds);
    }

    #[Test]
    public function seller_consegue_vincular_e_desvincular_item_do_proprio_veiculo(): void
    {
        $this->rls->beginTransaction();
        $this->setContext($this->sellerId, 'seller');
        $newAmenityId = $this->insertAmenity();
        $this->rls->prepare('INSERT INTO vehicle_amenity_links (vehicle_id, amenity_id) VALUES (?, ?)')
            ->execute([$this->vehicleId, $newAmenityId]);
        $afterInsert = $this->queryLinkedVehicleIds();
        $this->rls->prepare('DELETE FROM vehicle_amenity_links WHERE vehicle_id = ? AND amenity_id = ?')
            ->execute([$this->vehicleId, $newAmenityId]);
        $afterDelete = $this->queryLinkedVehicleIds();
        $this->rls->rollBack();

        $this->assertCount(2, $afterInsert);
        $this->assertCount(1, $afterDelete);
    }

    #[Test]
    public function seller_nao_consegue_vincular_item_a_veiculo_de_outro_seller(): void
    {
        $this->rls->beginTransaction();
        $this->setContext($this->sellerId, 'seller');

        try {
            $this->expectException(\PDOException::class);
            $this->rls->prepare('INSERT INTO vehicle_amenity_links (vehicle_id, amenity_id) VALUES (?, ?)')
                ->execute([$this->otherVehicleId, $this->amenityId]);
        } finally {
            $this->rls->rollBack();
        }
    }

    private function setContext(string $userId, string $role): void
    {
        $this->rls->exec('SET LOCAL app.current_user_id = ' . $this->rls->quote($userId));
        $this->rls->exec('SET LOCAL app.current_user_role = ' . $this->rls->quote($role));
    }

    /** @return list<string> */
    private function queryCatalogIds(): array
    {
        $statement = $this->rls->query('SELECT id FROM vehicle_amenity_catalog');
        $ids = [];

        foreach ($statement === false ? [] : $statement->fetchAll() as $row) {
            \assert(is_array($row) && is_string($row['id']));
            $ids[] = $row['id'];
        }

        return $ids;
    }

    /** @return list<string> */
    private function queryLinkedVehicleIds(): array
    {
        $statement = $this->rls->query('SELECT vehicle_id FROM vehicle_amenity_links');
        $ids = [];

        foreach ($statement === false ? [] : $statement->fetchAll() as $row) {
            \assert(is_array($row) && is_string($row['vehicle_id']));
            $ids[] = $row['vehicle_id'];
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

    private function insertAmenity(): string
    {
        $statement = $this->admin->prepare(<<<'SQL'
            INSERT INTO vehicle_amenity_catalog (code, label, position)
            VALUES (:code, 'Item RLS', 0)
            RETURNING id
            SQL);
        $statement->execute(['code' => 'item-rls-' . bin2hex(random_bytes(4))]);

        return (string) $statement->fetchColumn();
    }

    private function insertLink(string $vehicleId, string $amenityId): void
    {
        $this->admin->prepare('INSERT INTO vehicle_amenity_links (vehicle_id, amenity_id) VALUES (?, ?)')
            ->execute([$vehicleId, $amenityId]);
    }
}
