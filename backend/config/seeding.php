<?php

declare(strict_types=1);

use App\Env;

return [
    // Foto real via Wikimedia é pesado (rede + conversão WebP) pra rodar em CI --
    // desligado por default, cai pro placeholder de GD (sem rede).
    'fetch_real_images' => Env::bool('SEED_FETCH_REAL_IMAGES', false),
];
