<?php

declare(strict_types=1);

use App\Infrastructure\Database\Migration;

return new class () implements Migration {
    public function up(\PDO $pdo): void
    {
        $pdo->exec("CREATE TYPE user_status AS ENUM ('active', 'trashed', 'deleted')");
        $pdo->exec("ALTER TABLE users ADD COLUMN status user_status NOT NULL DEFAULT 'active'");
        $pdo->exec('ALTER TABLE users ADD COLUMN anonymized_at timestamptz');
        $pdo->exec('CREATE INDEX users_status_idx ON users (status)');
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP INDEX IF EXISTS users_status_idx');
        $pdo->exec('ALTER TABLE users DROP COLUMN anonymized_at');
        $pdo->exec('ALTER TABLE users DROP COLUMN status');
        $pdo->exec('DROP TYPE user_status');
    }
};
