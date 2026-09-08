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
final class AppointmentRlsPolicyTest extends TestCase
{
    private \PDO $admin;
    private \PDO $rls;
    private string $sellerId;
    private string $otherSellerId;
    private string $customerId;
    private string $dealershipId;
    private string $otherDealershipId;
    private string $vehicleId;
    private string $otherVehicleId;
    private string $appointmentId;
    private string $otherAppointmentId;
    private string $completedAppointmentId;

    protected function setUp(): void
    {
        $this->admin = TestDatabase::connect()->pdo();

        $this->sellerId = $this->insertUser('rls-appt-seller@example.com', 'seller');
        $this->otherSellerId = $this->insertUser('rls-appt-other@example.com', 'seller');
        $this->customerId = $this->insertUser('rls-appt-customer@example.com', 'customer');
        $this->dealershipId = $this->insertDealership($this->sellerId, 'RLS Appointment Center');
        $this->otherDealershipId = $this->insertDealership($this->otherSellerId, 'RLS Appointment Other');
        $this->vehicleId = $this->insertVehicle($this->dealershipId);
        $this->otherVehicleId = $this->insertVehicle($this->otherDealershipId);

        $this->appointmentId = $this->insertAppointment($this->vehicleId, 'pending');
        $this->otherAppointmentId = $this->insertAppointment($this->otherVehicleId, 'pending');
        $this->completedAppointmentId = $this->insertAppointment($this->vehicleId, 'completed');

        $this->rls = TestDatabase::connectAsApp()->pdo();
    }

    protected function tearDown(): void
    {
        $statement = $this->admin->prepare('DELETE FROM appointments WHERE id IN (?, ?, ?)');
        $statement->execute([$this->appointmentId, $this->otherAppointmentId, $this->completedAppointmentId]);
        $statement = $this->admin->prepare('DELETE FROM vehicles WHERE id IN (?, ?)');
        $statement->execute([$this->vehicleId, $this->otherVehicleId]);
        $statement = $this->admin->prepare('DELETE FROM dealerships WHERE id IN (?, ?)');
        $statement->execute([$this->dealershipId, $this->otherDealershipId]);
        $statement = $this->admin->prepare('DELETE FROM users WHERE id IN (?, ?, ?)');
        $statement->execute([$this->sellerId, $this->otherSellerId, $this->customerId]);
    }

    #[Test]
    public function seller_so_enxerga_agendamentos_das_proprias_concessionarias(): void
    {
        $this->rls->beginTransaction();
        $this->setContext($this->sellerId, 'seller');
        $ids = $this->queryAppointmentIds();
        $this->rls->rollBack();

        $this->assertContains($this->appointmentId, $ids);
        $this->assertContains($this->completedAppointmentId, $ids);
        $this->assertNotContains($this->otherAppointmentId, $ids);
    }

    #[Test]
    public function admin_enxerga_qualquer_agendamento(): void
    {
        $this->rls->beginTransaction();
        $this->setContext($this->sellerId, 'admin');
        $ids = $this->queryAppointmentIds();
        $this->rls->rollBack();

        $this->assertContains($this->appointmentId, $ids);
        $this->assertContains($this->otherAppointmentId, $ids);
    }

    #[Test]
    public function sem_contexto_setado_nenhuma_linha_e_retornada(): void
    {
        $this->rls->beginTransaction();
        $ids = $this->queryAppointmentIds();
        $this->rls->rollBack();

        $this->assertSame([], $ids);
    }

    #[Test]
    public function seller_nao_consegue_atualizar_agendamento_de_outro_seller(): void
    {
        $this->rls->beginTransaction();
        $this->setContext($this->sellerId, 'seller');
        $statement = $this->rls->prepare("UPDATE appointments SET status = 'confirmed' WHERE id = ?");
        $statement->execute([$this->otherAppointmentId]);
        $affected = $statement->rowCount();
        $this->rls->rollBack();

        $this->assertSame(0, $affected);
    }

    /** Sem request HTTP não há identidade pra setar -- é o caminho do `POST /appointments` público e da rotina agendada. */
    #[Test]
    public function contexto_de_servico_insere_enxerga_e_atualiza_qualquer_agendamento(): void
    {
        $this->rls->beginTransaction();
        $this->rls->exec("SET LOCAL app.is_service_context = 'true'");

        $insert = $this->rls->prepare(<<<'SQL'
            INSERT INTO appointments (vehicle_id, user_id, scheduled_at, customer_name, customer_email, customer_phone, status)
            VALUES (?, ?, now(), 'Service Insert', 'service@example.com', '11900000000', 'pending')
            RETURNING id
            SQL);
        $insert->execute([$this->vehicleId, $this->customerId]);
        $insertedId = (string) $insert->fetchColumn();

        $ids = $this->queryAppointmentIds();

        $update = $this->rls->prepare("UPDATE appointments SET status = 'confirmed' WHERE id = ?");
        $update->execute([$this->otherAppointmentId]);
        $affected = $update->rowCount();

        $this->rls->rollBack();

        $this->assertNotSame('', $insertedId);
        $this->assertContains($this->appointmentId, $ids);
        $this->assertContains($this->otherAppointmentId, $ids);
        $this->assertSame(1, $affected);
    }

    /** O motor de disponibilidade roda em contexto anônimo -- precisa ver o que está `pending`/`confirmed`. */
    #[Test]
    public function contexto_de_leitura_publica_enxerga_agendamento_pending(): void
    {
        $this->rls->beginTransaction();
        $this->rls->exec("SET LOCAL app.is_public_read = 'true'");
        $ids = $this->queryAppointmentIds();
        $this->rls->rollBack();

        $this->assertContains($this->appointmentId, $ids);
    }

    #[Test]
    public function contexto_de_leitura_publica_esconde_agendamento_completed(): void
    {
        $this->rls->beginTransaction();
        $this->rls->exec("SET LOCAL app.is_public_read = 'true'");
        $ids = $this->queryAppointmentIds();
        $this->rls->rollBack();

        $this->assertNotContains($this->completedAppointmentId, $ids);
    }

    private function setContext(string $userId, string $role): void
    {
        $this->rls->exec('SET LOCAL app.current_user_id = ' . $this->rls->quote($userId));
        $this->rls->exec('SET LOCAL app.current_user_role = ' . $this->rls->quote($role));
    }

    /** @return list<string> */
    private function queryAppointmentIds(): array
    {
        $statement = $this->rls->query('SELECT id FROM appointments');
        $ids = [];

        foreach ($statement === false ? [] : $statement->fetchAll() as $row) {
            \assert(is_array($row) && is_string($row['id']));
            $ids[] = $row['id'];
        }

        return $ids;
    }

    private function insertUser(string $email, string $role): string
    {
        $statement = $this->admin->prepare(<<<'SQL'
            INSERT INTO users (name, email, password, role)
            VALUES ('User RLS', :email, 'hash', :role)
            RETURNING id
            SQL);
        $statement->execute(['email' => $email, 'role' => $role]);

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
            VALUES (:dealership_id, 'Chevrolet', 'Onix', 50000.00)
            RETURNING id
            SQL);
        $statement->execute(['dealership_id' => $dealershipId]);

        return (string) $statement->fetchColumn();
    }

    private function insertAppointment(string $vehicleId, string $status): string
    {
        $statement = $this->admin->prepare(<<<'SQL'
            INSERT INTO appointments (vehicle_id, user_id, scheduled_at, customer_name, customer_email, customer_phone, status)
            VALUES (:vehicle_id, :user_id, now(), 'Ada Lovelace', 'ada@example.com', '11999990000', :status)
            RETURNING id
            SQL);
        $statement->execute(['vehicle_id' => $vehicleId, 'user_id' => $this->customerId, 'status' => $status]);

        return (string) $statement->fetchColumn();
    }
}
