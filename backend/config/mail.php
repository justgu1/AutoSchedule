<?php

declare(strict_types=1);

return [
    'host' => getenv('MAIL_HOST') ?: '127.0.0.1',
    'port' => (int) (getenv('MAIL_PORT') ?: 1025),
    'from' => getenv('MAIL_FROM') ?: 'noreply@autoschedule.local',
    'frontend_url' => getenv('FRONTEND_URL') ?: 'http://localhost:5173',
];
