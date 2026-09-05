<?php

declare(strict_types=1);

use App\Infrastructure\Database\Seeder;

return new class () implements Seeder {
    public function run(\PDO $pdo): void
    {
        $statement = $pdo->prepare(
            'INSERT INTO users (name, email, password, role)
             VALUES (?, ?, ?, ?)
             ON CONFLICT (email) DO NOTHING'
        );

        $statement->execute([
            'Admin',
            'admin@autoschedule.local',
            password_hash('password', PASSWORD_ARGON2ID),
            'admin',
        ]);
    }
};
