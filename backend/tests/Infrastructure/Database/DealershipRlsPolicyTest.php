<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Database;

use App\Infrastructure\Database\PostgresConnection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Teste de integração: valida as policies de RLS de `dealerships` de
 * verdade, conectando como autoschedule_app (a role admin/pgsql é superuser
 * e sempre ignora RLS). Duas conexões/sessões diferentes -- fixture
 * commitada pela conexão admin, limpeza no tearDown é DELETE, não rollback.
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
        $this->admin = new PostgresConnection(
            driver: getenv('DB_DRIVER') ?: 'pgsql',
            host: getenv('DB_HOST') ?: '127.0.0.1',
            port: (int) (getenv('DB_PORT') ?: 5432),
            database: getenv('DB_DATABASE') ?: 'autoschedule',
            username: getenv('DB_USERNAME') ?: 'pgsql',
            password: getenv('DB_PASSWORD') ?: 'password',
        )->pdo();

        $this->sellerId = $this->insertSellerUser('rls-seller@example.com');
        $this->otherSellerId = $this->insertSellerUser('rls-other-seller@example.com');
        $this->dealershipId = $this->insertDealership($this->sellerId, 'RLS Auto Center');
        $this->otherDealershipId = $this->insertDealership($this->otherSellerId, 'RLS Other Center');
        $this->trashedDealershipId = $this->insertDealership($this->otherSellerId, 'RLS Trashed Center');
        $this->admin->exec("UPDATE dealerships SET status = 'trashed' WHERE id = " . $this->admin->quote($this->trashedDealershipId));

        $this->rls = new PostgresConnection(
            driver: getenv('DB_DRIVER') ?: 'pgsql',
            host: getenv('DB_HOST') ?: '127.0.0.1',
            port: (int) (getenv('DB_PORT') ?: 5432),
            database: getenv('DB_DATABASE') ?: 'autoschedule',
            username: getenv('DB_APP_USERNAME') ?: 'autoschedule_app',
            password: getenv('DB_APP_PASSWORD') ?: 'changeme',
        )->pdo();
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

    /**
     * `AuthContextMiddleware` compõe `is_public_read` com o contexto
     * autenticado normal (não é alternativo) -- um seller batendo em
     * `GET /dealerships/{id}` de outro seller precisa das duas coisas juntas
     * pra cair no fallback público em vez de tomar 404. Sem essa composição,
     * `seller_so_enxerga_a_propria_concessionaria` (acima) continuaria
     * verdade só nas rotas de gerenciamento -- aqui é o caso da rota pública.
     */
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

    /**
     * `GET /dealerships/{id}` (rota `publicRead`) só enxerga concessionária
     * `active` -- trashed continua invisível mesmo com a flag setada, e a
     * flag sozinha (sem `current_user_id`/role de dono/admin) não abre nada
     * além disso.
     */
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

    /**
     * Scheduler/worker rodam sem request HTTP, sem `current_user_id`/role pra
     * setar -- a mesma policy de serviço que o login/registro usa em `users`
     * é o que deixa o purge agendado e o job de foto enxergarem a linha.
     */
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
