<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Database;

use App\Domain\Shared\Email;
use App\Domain\User\User;
use App\Domain\User\UserRole;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;

/**
 * Conecta como autoschedule_app porque a role admin é superuser e ignora RLS.
 * Duas sessões: a fixture precisa ser commitada, então a limpeza é DELETE e não rollback.
 */
#[Group('integration')]
final class RlsPolicyTest extends TestCase
{
    private \PDO $admin;
    private \PDO $rls;
    private User $customer;
    private User $otherCustomer;

    protected function setUp(): void
    {
        $this->admin = TestDatabase::connect()->pdo();

        $this->customer = User::register('Customer RLS', new Email('rls-customer@example.com'), null, 'secret', UserRole::Customer);
        $this->otherCustomer = User::register('Other Customer RLS', new Email('rls-other@example.com'), null, 'secret', UserRole::Customer);
        $this->insertUser($this->customer);
        $this->insertUser($this->otherCustomer);

        $this->rls = TestDatabase::connectAsApp()->pdo();
    }

    protected function tearDown(): void
    {
        $statement = $this->admin->prepare('DELETE FROM users WHERE id IN (?, ?)');
        $statement->execute([$this->customer->id, $this->otherCustomer->id]);
    }

    #[Test]
    public function customer_so_enxerga_a_propria_linha(): void
    {
        $this->rls->beginTransaction();
        $this->setContext($this->customer->id, 'customer');
        $ids = array_column($this->rls->query('SELECT id FROM users')->fetchAll(), 'id');
        $this->rls->rollBack();

        $this->assertSame([$this->customer->id], $ids);
    }

    #[Test]
    public function admin_enxerga_qualquer_linha(): void
    {
        $this->rls->beginTransaction();
        $this->setContext($this->customer->id, 'admin');
        $ids = array_column($this->rls->query('SELECT id FROM users')->fetchAll(), 'id');
        $this->rls->rollBack();

        $this->assertContains($this->customer->id, $ids);
        $this->assertContains($this->otherCustomer->id, $ids);
    }

    #[Test]
    public function contexto_de_servico_enxerga_qualquer_linha(): void
    {
        $this->rls->beginTransaction();
        $this->rls->exec("SET LOCAL app.is_service_context = 'true'");
        $ids = array_column($this->rls->query('SELECT id FROM users')->fetchAll(), 'id');
        $this->rls->rollBack();

        $this->assertContains($this->customer->id, $ids);
        $this->assertContains($this->otherCustomer->id, $ids);
    }

    #[Test]
    public function sem_contexto_setado_nenhuma_linha_e_retornada(): void
    {
        $this->rls->beginTransaction();
        $ids = array_column($this->rls->query('SELECT id FROM users')->fetchAll(), 'id');
        $this->rls->rollBack();

        $this->assertSame([], $ids);
    }

    /** Só o seller dono de concessionária ativa fica visível na leitura pública; customer nunca. */
    #[Test]
    public function contexto_de_leitura_publica_enxerga_so_seller_com_concessionaria_ativa(): void
    {
        $sellerWithDealership = User::register('Seller RLS', new Email('rls-seller-public@example.com'), null, 'secret', UserRole::Seller);
        $sellerWithoutDealership = User::register('Seller Sem Loja RLS', new Email('rls-seller-nodealership@example.com'), null, 'secret', UserRole::Seller);
        $this->insertUser($sellerWithDealership);
        $this->insertUser($sellerWithoutDealership);
        $this->admin->exec(<<<SQL
            INSERT INTO dealerships (owner_user_id, name, slug, zip_code, address, number, neighborhood, city, state)
            VALUES ({$this->admin->quote($sellerWithDealership->id)}, 'RLS Public Center', 'rls-public-center', '00000-000', 'Rua', '1', 'Bairro', 'Cidade', 'SP')
            SQL);

        try {
            $this->rls->beginTransaction();
            $this->rls->exec("SET LOCAL app.is_public_read = 'true'");
            $ids = array_column($this->rls->query('SELECT id FROM users')->fetchAll(), 'id');
            $this->rls->rollBack();

            $this->assertContains($sellerWithDealership->id, $ids);
            $this->assertNotContains($sellerWithoutDealership->id, $ids);
            $this->assertNotContains($this->customer->id, $ids);
        } finally {
            $this->admin->exec('DELETE FROM dealerships WHERE owner_user_id = ' . $this->admin->quote($sellerWithDealership->id));
            $statement = $this->admin->prepare('DELETE FROM users WHERE id IN (?, ?)');
            $statement->execute([$sellerWithDealership->id, $sellerWithoutDealership->id]);
        }
    }

    #[Test]
    public function contexto_de_servico_consegue_inserir(): void
    {
        $newUser = User::register('Registered RLS', new Email('rls-registered@example.com'), null, 'secret', UserRole::Customer);

        $this->rls->beginTransaction();
        $this->rls->exec("SET LOCAL app.is_service_context = 'true'");
        $this->insertUser($newUser, $this->rls);
        $this->rls->rollBack();

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function sem_contexto_setado_insert_falha(): void
    {
        $newUser = User::register('Blocked RLS', new Email('rls-blocked@example.com'), null, 'secret', UserRole::Customer);

        $this->rls->beginTransaction();

        try {
            $this->expectException(\PDOException::class);
            $this->insertUser($newUser, $this->rls);
        } finally {
            $this->rls->rollBack();
        }
    }

    private function setContext(string $userId, string $role): void
    {
        $this->rls->exec('SET LOCAL app.current_user_id = ' . $this->rls->quote($userId));
        $this->rls->exec('SET LOCAL app.current_user_role = ' . $this->rls->quote($role));
    }

    private function insertUser(User $user, ?\PDO $pdo = null): void
    {
        $statement = ($pdo ?? $this->admin)->prepare(<<<'SQL'
            INSERT INTO users (id, name, email, phone, password, role, password_set_at, created_at, updated_at)
            VALUES (:id, :name, :email, :phone, :password, :role, :password_set_at, :created_at, :updated_at)
            SQL);

        $statement->execute([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'password' => $user->passwordHash,
            'role' => $user->role->value,
            'password_set_at' => $user->passwordSetAt?->format(DATE_ATOM),
            'created_at' => $user->createdAt->format(DATE_ATOM),
            'updated_at' => $user->updatedAt->format(DATE_ATOM),
        ]);
    }
}
