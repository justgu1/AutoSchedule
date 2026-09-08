<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Persistence;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;

/**
 * Conecta como autoschedule_app porque a role admin é superuser e ignora RLS.
 * Duas sessões: a fixture é commitada pela admin, então a limpeza é DELETE e não rollback.
 */
#[Group('integration')]
final class VehicleRlsPolicyTest extends TestCase
{
    private \PDO $admin;
    private \PDO $rls;
    private string $sellerId;
    private string $otherSellerId;
    private string $dealershipId;
    private string $otherDealershipId;
    private string $trashedDealershipId;
    private string $vehicleId;
    private string $otherVehicleId;
    private string $hiddenVehicleId;

    protected function setUp(): void
    {
        $this->admin = TestDatabase::connect()->pdo();

        $this->sellerId = $this->insertSellerUser('rls-vehicle-seller@example.com');
        $this->otherSellerId = $this->insertSellerUser('rls-vehicle-other@example.com');
        $this->dealershipId = $this->insertDealership($this->sellerId, 'RLS Vehicle Center');
        $this->otherDealershipId = $this->insertDealership($this->otherSellerId, 'RLS Vehicle Other');
        $this->trashedDealershipId = $this->insertDealership($this->otherSellerId, 'RLS Vehicle Trashed');
        $this->admin->exec("UPDATE dealerships SET status = 'trashed' WHERE id = " . $this->admin->quote($this->trashedDealershipId));

        $this->vehicleId = $this->insertVehicle($this->dealershipId, 'Chevrolet');
        $this->otherVehicleId = $this->insertVehicle($this->otherDealershipId, 'Fiat');
        $this->hiddenVehicleId = $this->insertVehicle($this->trashedDealershipId, 'Renault');

        $this->rls = TestDatabase::connectAsApp()->pdo();
    }

    protected function tearDown(): void
    {
        $statement = $this->admin->prepare('DELETE FROM vehicles WHERE id IN (?, ?, ?)');
        $statement->execute([$this->vehicleId, $this->otherVehicleId, $this->hiddenVehicleId]);
        $statement = $this->admin->prepare('DELETE FROM dealerships WHERE id IN (?, ?, ?)');
        $statement->execute([$this->dealershipId, $this->otherDealershipId, $this->trashedDealershipId]);
        $statement = $this->admin->prepare('DELETE FROM users WHERE id IN (?, ?)');
        $statement->execute([$this->sellerId, $this->otherSellerId]);
    }

    #[Test]
    public function seller_so_enxerga_os_veiculos_das_proprias_concessionarias(): void
    {
        $this->rls->beginTransaction();
        $this->setContext($this->sellerId, 'seller');
        $ids = $this->queryVehicleIds();
        $this->rls->rollBack();

        $this->assertSame([$this->vehicleId], $ids);
    }

    #[Test]
    public function admin_enxerga_qualquer_veiculo(): void
    {
        $this->rls->beginTransaction();
        $this->setContext($this->sellerId, 'admin');
        $ids = $this->queryVehicleIds();
        $this->rls->rollBack();

        $this->assertContains($this->vehicleId, $ids);
        $this->assertContains($this->otherVehicleId, $ids);
    }

    #[Test]
    public function sem_contexto_setado_nenhuma_linha_e_retornada(): void
    {
        $this->rls->beginTransaction();
        $ids = $this->queryVehicleIds();
        $this->rls->rollBack();

        $this->assertSame([], $ids);
    }

    /** O dono do veículo vem do payload, então a policy de INSERT precisa checar a concessionária, não só a role. */
    #[Test]
    public function seller_nao_consegue_inserir_veiculo_em_concessionaria_de_outro_seller(): void
    {
        $this->rls->beginTransaction();
        $this->setContext($this->sellerId, 'seller');

        try {
            $this->expectException(\PDOException::class);
            $this->rls->prepare(<<<'SQL'
                INSERT INTO vehicles (dealership_id, brand, model, price)
                VALUES (?, 'Invadido', 'Modelo', 1000.00)
                SQL)->execute([$this->otherDealershipId]);
        } finally {
            $this->rls->rollBack();
        }
    }

    #[Test]
    public function seller_nao_consegue_atualizar_veiculo_de_outro_seller(): void
    {
        $this->rls->beginTransaction();
        $this->setContext($this->sellerId, 'seller');
        $statement = $this->rls->prepare('UPDATE vehicles SET brand = ? WHERE id = ?');
        $statement->execute(['Hacked', $this->otherVehicleId]);
        $affected = $statement->rowCount();
        $this->rls->rollBack();

        $this->assertSame(0, $affected);
    }

    /** Sem request HTTP não há identidade pra setar, então o background depende da policy de serviço. */
    #[Test]
    public function contexto_de_servico_enxerga_e_atualiza_qualquer_veiculo(): void
    {
        $this->rls->beginTransaction();
        $this->rls->exec("SET LOCAL app.is_service_context = 'true'");
        $ids = $this->queryVehicleIds();
        $statement = $this->rls->prepare('UPDATE vehicles SET brand = ? WHERE id = ?');
        $statement->execute(['Updated by service', $this->otherVehicleId]);
        $affected = $statement->rowCount();
        $this->rls->rollBack();

        $this->assertContains($this->vehicleId, $ids);
        $this->assertContains($this->otherVehicleId, $ids);
        $this->assertSame(1, $affected);
    }

    #[Test]
    public function contexto_de_leitura_publica_enxerga_veiculo_ativo_de_concessionaria_ativa(): void
    {
        $this->rls->beginTransaction();
        $this->rls->exec("SET LOCAL app.is_public_read = 'true'");
        $ids = $this->queryVehicleIds();
        $this->rls->rollBack();

        $this->assertContains($this->vehicleId, $ids);
        $this->assertContains($this->otherVehicleId, $ids);
    }

    /** Concessionária na lixeira some da vitrine junto com o estoque dela, sem precisar escrever em `vehicles`. */
    #[Test]
    public function contexto_de_leitura_publica_esconde_veiculo_de_concessionaria_na_lixeira(): void
    {
        $this->rls->beginTransaction();
        $this->rls->exec("SET LOCAL app.is_public_read = 'true'");
        $ids = $this->queryVehicleIds();
        $this->rls->rollBack();

        $this->assertNotContains($this->hiddenVehicleId, $ids);
    }

    #[Test]
    public function contexto_de_leitura_publica_esconde_veiculo_na_lixeira(): void
    {
        $this->admin->exec("UPDATE vehicles SET status = 'trashed' WHERE id = " . $this->admin->quote($this->vehicleId));

        $this->rls->beginTransaction();
        $this->rls->exec("SET LOCAL app.is_public_read = 'true'");
        $ids = $this->queryVehicleIds();
        $this->rls->rollBack();

        $this->assertNotContains($this->vehicleId, $ids);
    }

    private function setContext(string $userId, string $role): void
    {
        $this->rls->exec('SET LOCAL app.current_user_id = ' . $this->rls->quote($userId));
        $this->rls->exec('SET LOCAL app.current_user_role = ' . $this->rls->quote($role));
    }

    /** @return list<string> */
    private function queryVehicleIds(): array
    {
        $statement = $this->rls->query('SELECT id FROM vehicles');
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

    private function insertVehicle(string $dealershipId, string $brand): string
    {
        $statement = $this->admin->prepare(<<<'SQL'
            INSERT INTO vehicles (dealership_id, brand, model, price)
            VALUES (:dealership_id, :brand, 'Modelo', 50000.00)
            RETURNING id
            SQL);
        $statement->execute(['dealership_id' => $dealershipId, 'brand' => $brand]);

        return (string) $statement->fetchColumn();
    }
}
