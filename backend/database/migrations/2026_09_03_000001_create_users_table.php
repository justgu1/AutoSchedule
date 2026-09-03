<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migration;

return new class implements Migration {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE users (
                id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                name varchar(120) NOT NULL,
                email varchar(180) NOT NULL UNIQUE,
                phone varchar(20),
                password varchar(255) NOT NULL,
                role varchar(20) NOT NULL CHECK (role IN ('admin', 'seller', 'customer')),
                password_set_at timestamptz,
                email_verified_at timestamptz,
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now(),
                deleted_at timestamptz
            )
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE users');
    }
};
