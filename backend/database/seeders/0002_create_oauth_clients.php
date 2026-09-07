<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Seeder;

return new class () implements Seeder {
    public function run(\PDO $pdo): void
    {
        $statement = $pdo->prepare(<<<'SQL'
            INSERT INTO oauth_clients (client_id, name, type, secret_hash, allowed_grant_types, redirect_uris, allowed_scopes)
            VALUES (:client_id, :name, :type, :secret_hash, :allowed_grant_types, :redirect_uris, :allowed_scopes)
            ON CONFLICT (client_id) DO NOTHING
            SQL);

        // Client público: SPA first-party não tem onde guardar secret.
        $statement->execute([
            'client_id' => 'autoschedule-web',
            'name' => 'AutoSchedule Web',
            'type' => 'public',
            'secret_hash' => null,
            'allowed_grant_types' => '{password,refresh_token,google}',
            'redirect_uris' => null,
            'allowed_scopes' => '{profile:read,profile:write,users:read,users:write}',
        ]);

        // Client confidencial: sem consumidor ainda, plumbing pra futura chamada serviço-a-serviço (m2m).
        $serviceSecret = bin2hex(random_bytes(24));

        $statement->execute([
            'client_id' => 'autoschedule-service',
            'name' => 'AutoSchedule Service',
            'type' => 'confidential',
            'secret_hash' => password_hash($serviceSecret, PASSWORD_ARGON2ID),
            'allowed_grant_types' => '{client_credentials}',
            'redirect_uris' => null,
            'allowed_scopes' => '{service:internal}',
        ]);

        // Num re-seed o insert não acontece, e este secret não bateria com o hash já guardado.
        if ($statement->rowCount() > 0) {
            fwrite(STDOUT, "autoschedule-service client secret: {$serviceSecret}\n");
        }
    }
};
