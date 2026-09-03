<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Application;

$app = new Application();

header('Content-Type: application/json');

echo json_encode([
    'message' => 'AutoSchedule API',
    'timezone' => $app->config('timezone'),
    'debug' => $app->config('debug'),
]);