<?php

declare(strict_types=1);

use App\Env;

return [
    'client_id' => Env::string('GOOGLE_CLIENT_ID', ''),
];
