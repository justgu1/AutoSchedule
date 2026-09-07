<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Schema\Migration;

/**
 * O UNIQUE de `users.email` é case-sensitive, então uma linha em caixa mista fica inalcançável agora
 * que `Email` normaliza na entrada. Colisão aqui é erro que exige decisão humana -- falhar é o certo.
 */
return new class () implements Migration {
    public function up(\PDO $pdo): void
    {
        $pdo->exec('UPDATE users SET email = lower(trim(email)) WHERE email <> lower(trim(email))');
        $pdo->exec('UPDATE user_identities SET email = lower(trim(email)) WHERE email <> lower(trim(email))');
        $pdo->exec('UPDATE dealerships SET email = lower(trim(email)) WHERE email IS NOT NULL AND email <> lower(trim(email))');
    }

    public function down(\PDO $pdo): void
    {
        // Normalização não tem volta: a caixa original não é recuperável.
    }
};
