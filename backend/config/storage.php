<?php

declare(strict_types=1);

use App\Env;

$endpoint = Env::string('S3_ENDPOINT', 'http://minio:9000');
$bucket = Env::string('S3_BUCKET', 'autoschedule');

return [
    'endpoint' => $endpoint,
    'bucket' => $bucket,
    'region' => Env::string('S3_REGION', 'us-east-1'),
    'access_key' => Env::string('S3_ACCESS_KEY', 'admin'),
    'secret_key' => Env::string('S3_SECRET_KEY', 'password'),
    'public_url' => Env::string('S3_PUBLIC_URL', sprintf('%s/%s', rtrim($endpoint, '/'), $bucket)),
    // Onde o upload aguarda confirmação do MinIO, compartilhado com o worker.
    'temp_path' => Env::string('TEMP_STORAGE_PATH', sys_get_temp_dir()),
];
