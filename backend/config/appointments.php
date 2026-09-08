<?php

declare(strict_types=1);

use App\Env;

return [
    // Só conta a partir de quando o e-mail de confirmação sai, não da criação -- reserva atrás de
    // outro veículo em uso pode esperar bem mais que isso antes de sequer poder ser confirmada.
    'pending_ttl_seconds' => Env::int('APPOINTMENT_PENDING_TTL', 1800),
];
