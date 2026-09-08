<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

App\Bootstrap\HttpKernel::boot()
    ->handle(App\Infrastructure\Http\Request::fromGlobals())
    ->send();
