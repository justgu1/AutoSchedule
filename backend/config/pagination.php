<?php

declare(strict_types=1);

return [
    'default_per_page' => (int) (getenv('PAGINATION_DEFAULT_PER_PAGE') ?: 20),
    'max_per_page' => (int) (getenv('PAGINATION_MAX_PER_PAGE') ?: 100),
];
