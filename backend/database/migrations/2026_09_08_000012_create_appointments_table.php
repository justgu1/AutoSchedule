<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Schema\Migration;

return new class () implements Migration {
    public function up(\PDO $pdo): void
    {
        $pdo->exec("CREATE TYPE appointment_status AS ENUM ('pending', 'confirmed', 'completed', 'cancelled', 'no_show')");

        $pdo->exec(<<<'SQL'
            CREATE TABLE appointments (
                id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                vehicle_id uuid NOT NULL REFERENCES vehicles(id),
                user_id uuid NOT NULL REFERENCES users(id),
                scheduled_at timestamptz NOT NULL,
                customer_name varchar(120) NOT NULL,
                customer_email varchar(180) NOT NULL,
                customer_phone varchar(20) NOT NULL,
                status appointment_status NOT NULL DEFAULT 'pending',
                confirmation_token_hash varchar(64),
                confirmation_email_sent_at timestamptz,
                expires_at timestamptz,
                picked_up_at timestamptz,
                released_at timestamptz,
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now()
            )
            SQL);

        $pdo->exec('CREATE INDEX appointments_vehicle_id_idx ON appointments (vehicle_id)');
        $pdo->exec('CREATE INDEX appointments_user_id_idx ON appointments (user_id)');

        // Backstop de concorrência: duas requisições pro mesmo veículo/horário, uma vira 409 (Statement::translate), não 500.
        $pdo->exec(<<<'SQL'
            CREATE UNIQUE INDEX appointments_active_vehicle_time_unique
            ON appointments (vehicle_id, scheduled_at)
            WHERE status IN ('pending', 'confirmed')
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE appointments');
        $pdo->exec('DROP TYPE appointment_status');
    }
};
