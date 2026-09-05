<?php

declare(strict_types=1);

use App\Infrastructure\Database\Seeder;

return new class () implements Seeder {
    public function run(\PDO $pdo): void
    {
        $statement = $pdo->prepare(<<<'SQL'
            INSERT INTO oauth_clients (client_id, name, type, secret_hash, allowed_grant_types, redirect_uris, allowed_scopes)
            VALUES (:client_id, :name, :type, :secret_hash, :allowed_grant_types, :redirect_uris, :allowed_scopes)
            ON CONFLICT (client_id) DO NOTHING
            SQL);

        // Client público: sem secret, usado pelo SPA first-party (login direto
        // email+senha + refresh_token, sem redirect).
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

        // Só imprime o secret quando essa execução realmente inseriu a linha --
        // ON CONFLICT DO NOTHING faz rowCount() ser 0 num re-seed, e o
        // $serviceSecret não bateria com o hash que já está guardado.
        if ($statement->rowCount() > 0) {
            fwrite(STDOUT, "autoschedule-service client secret: {$serviceSecret}\n");
        }
    }
};
