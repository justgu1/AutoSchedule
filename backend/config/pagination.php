<?php

declare(strict_types=1);

use App\Env;

return [
    'default_per_page' => Env::int('PAGINATION_DEFAULT_PER_PAGE', 20),
    'max_per_page' => Env::int('PAGINATION_MAX_PER_PAGE', 100),
];
