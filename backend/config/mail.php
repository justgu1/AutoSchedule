<?php

declare(strict_types=1);

return [
    // MAIL_DSN cobre SMTP real com auth/TLS (produção, ex: `smtps://user:pass@host:465`).
    // Sem MAIL_DSN, monta o de sempre a partir de host/porta -- sem auth, é o que o
    // Mailpit local espera; nada muda pra quem já usa `docker-compose.yaml` hoje.
    'dsn' => getenv('MAIL_DSN') ?: sprintf('smtp://%s:%d', getenv('MAIL_HOST') ?: '127.0.0.1', (int) (getenv('MAIL_PORT') ?: 1025)),
    'from' => getenv('MAIL_FROM') ?: 'noreply@autoschedule.local',
    'frontend_url' => getenv('FRONTEND_URL') ?: 'http://localhost:5173',
];
