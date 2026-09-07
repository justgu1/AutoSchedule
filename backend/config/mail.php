<?php

declare(strict_types=1);

use App\Env;

return [
    // Sem MAIL_DSN, monta o DSN sem auth que o Mailpit local espera; produção passa o DSN completo.
    'dsn' => Env::stringOrNull('MAIL_DSN')
        ?? sprintf('smtp://%s:%d', Env::string('MAIL_HOST', '127.0.0.1'), Env::int('MAIL_PORT', 1025)),
    'from' => Env::string('MAIL_FROM', 'noreply@autoschedule.local'),
    'frontend_url' => Env::string('FRONTEND_URL', 'http://localhost:5173'),
];
