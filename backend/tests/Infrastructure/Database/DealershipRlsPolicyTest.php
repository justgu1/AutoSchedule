<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Database;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;

/**
 * Conecta como autoschedule_app porque a role admin é superuser e ignora RLS.
 * Duas sessões: a fixture é commitada pela admin, então a limpeza é DELETE e não rollback.
 */
#[Group('integration')]
final class DealershipRlsPolicyTest extends TestCase
{
    private \PDO $admin;
    private \PDO $rls;
    private string $sellerId;
    private string $otherSellerId;
    private string $dealershipId;
    private string $otherDealershipId;
    private string $trashedDealershipId;

    protected function setUp(): void
    {
        $this->admin = TestDatabase::connect()->pdo();

        $this->sellerId = $this->insertSellerUser('rls-seller@example.com');
        $this->otherSellerId = $this->insertSellerUser('rls-other-seller@example.com');
        $this->dealershipId = $this->insertDealership($this->sellerId, 'RLS Auto Center');
        $this->otherDealershipId = $this->insertDealership($this->otherSellerId, 'RLS Other Center');
        $this->trashedDealershipId = $this->insertDealership($this->otherSellerId, 'RLS Trashed Center');
        $this->admin->exec("UPDATE dealerships SET status = 'trashed' WHERE id = " . $this->admin->quote($this->trashedDealershipId));

        $this->rls = TestDatabase::connectAsApp()->pdo();
    }

    protected function tearDown(): void
    {
        $statement = $this->admin->prepare('DELETE FROM dealerships WHERE id IN (?, ?, ?)');
        $statement->execute([$this->dealershipId, $this->otherDealershipId, $this->trashedDealershipId]);
        $statement = $this->admin->prepare('DELETE FROM users WHERE id IN (?, ?)');
        $statement->execute([$this->sellerId, $this->otherSellerId]);
    }

    #[Test]
    public function seller_so_enxerga_a_propria_concessionaria(): void
    {
        $this->rls->beginTransaction();
        $this->setContext($this->sellerId, 'seller');
        $ids = $this->queryDealershipIds();
        $this->rls->rollBack();

        $this->assertSame([$this->dealershipId], $ids);
    }

    /** Sem compor a flag pública com a identidade, o seller autenticado tomaria 404 na concessionária alheia. */
    #[Test]
    public function seller_com_contexto_de_leitura_publica_tambem_enxerga_concessionaria_de_outro_seller(): void
    {
        $this->rls->beginTransaction();
        $this->setContext($this->sellerId, 'seller');
        $this->rls->exec("SET LOCAL app.is_public_read = 'true'");
        $ids = $this->queryDealershipIds();
        $this->rls->rollBack();

        $this->assertContains($this->dealershipId, $ids);
        $this->assertContains($this->otherDealershipId, $ids);
        $this->assertNotContains($this->trashedDealershipId, $ids);
    }

    #[Test]
    public function admin_enxerga_qualquer_concessionaria(): void
    {
        $this->rls->beginTransaction();
        $this->setContext($this->sellerId, 'admin');
        $ids = $this->queryDealershipIds();
        $this->rls->rollBack();

        $this->assertContains($this->dealershipId, $ids);
        $this->assertContains($this->otherDealershipId, $ids);
    }

    #[Test]
    public function sem_contexto_setado_nenhuma_linha_e_retornada(): void
    {
        $this->rls->beginTransaction();
        $ids = $this->queryDealershipIds();
        $this->rls->rollBack();

        $this->assertSame([], $ids);
    }

    /** A flag pública não é curinga: trashed segue invisível, e sozinha ela não abre mais nada. */
    #[Test]
    public function contexto_de_leitura_publica_enxerga_so_concessionaria_ativa(): void
    {
        $this->rls->beginTransaction();
        $this->rls->exec("SET LOCAL app.is_public_read = 'true'");
        $ids = $this->queryDealershipIds();
        $this->rls->rollBack();

        $this->assertContains($this->dealershipId, $ids);
        $this->assertContains($this->otherDealershipId, $ids);
        $this->assertNotContains($this->trashedDealershipId, $ids);
    }

    /** Sem request HTTP não há identidade pra setar, então o background depende da policy de serviço. */
    #[Test]
    public function contexto_de_servico_enxerga_e_atualiza_qualquer_linha(): void
    {
        $this->rls->beginTransaction();
        $this->rls->exec("SET LOCAL app.is_service_context = 'true'");
        $ids = $this->queryDealershipIds();
        $statement = $this->rls->prepare('UPDATE dealerships SET name = ? WHERE id = ?');
        $statement->execute(['Updated by service', $this->otherDealershipId]);
        $affected = $statement->rowCount();
        $this->rls->rollBack();

        $this->assertContains($this->dealershipId, $ids);
        $this->assertContains($this->otherDealershipId, $ids);
        $this->assertSame(1, $affected);
    }

    #[Test]
    public function seller_nao_consegue_atualizar_concessionaria_de_outro_seller(): void
    {
        $this->rls->beginTransaction();
        $this->setContext($this->sellerId, 'seller');
        $statement = $this->rls->prepare('UPDATE dealerships SET name = ? WHERE id = ?');
        $statement->execute(['Hacked', $this->otherDealershipId]);
        $affected = $statement->rowCount();
        $this->rls->rollBack();

        $this->assertSame(0, $affected);
    }

    #[Test]
    public function customer_nao_consegue_inserir_concessionaria(): void
    {
        $this->rls->beginTransaction();
        $this->setContext($this->sellerId, 'customer');

        try {
            $this->expectException(\PDOException::class);
            $this->rls->prepare(<<<'SQL'
                INSERT INTO dealerships (owner_user_id, name, slug, zip_code, address, number, neighborhood, city, state)
                VALUES (?, 'Blocked', 'blocked-slug', '00000-000', 'Rua', '1', 'Bairro', 'Cidade', 'SP')
                SQL)->execute([$this->sellerId]);
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
    private function queryDealershipIds(): array
    {
        $statement = $this->rls->query('SELECT id FROM dealerships');
        $ids = [];

        foreach ($statement === false ? [] : $statement->fetchAll() as $row) {
            \assert(is_string($row['id']));
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
}
