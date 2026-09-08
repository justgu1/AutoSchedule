<?php

declare(strict_types=1);

use App\Env;

return [
    'allowed_origins' => array_values(array_filter(explode(',', Env::string('CORS_ALLOWED_ORIGINS', '')))),
];
