<?php

declare(strict_types=1);

use App\Infrastructure\Database\Seeder;

return new class implements Seeder {
    public function run(\PDO $pdo): void
    {
        $statement = $pdo->prepare(<<<'SQL'
            INSERT INTO oauth_clients (client_id, name, type, secret_hash, allowed_grant_types, redirect_uris, allowed_scopes)
            VALUES (:client_id, :name, :type, :secret_hash, :allowed_grant_types, :redirect_uris, :allowed_scopes)
            ON CONFLICT (client_id) DO NOTHING
            SQL);

        // Public client: no secret, used by the first-party SPA (authorization_code + PKCE, headless).
        $statement->execute([
            'client_id' => 'autoschedule-web',
            'name' => 'AutoSchedule Web',
            'type' => 'public',
            'secret_hash' => null,
            'allowed_grant_types' => '{authorization_code,refresh_token}',
            'redirect_uris' => '{urn:autoschedule:headless}',
            'allowed_scopes' => '{profile:read,profile:write,users:read,users:write}',
        ]);

        // Confidential client: no consumer yet, plumbing for future service-to-service (m2m) calls.
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

        // Only print the secret when this run actually inserted the row -- ON CONFLICT DO
        // NOTHING means rowCount() is 0 on a re-seed, and $serviceSecret would not match
        // whatever hash is already stored.
        if ($statement->rowCount() > 0) {
            fwrite(STDOUT, "autoschedule-service client secret: {$serviceSecret}\n");
        }
    }
};
