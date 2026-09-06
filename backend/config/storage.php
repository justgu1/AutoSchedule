<?php

declare(strict_types=1);

return [
    'endpoint' => getenv('S3_ENDPOINT') ?: 'http://minio:9000',
    'bucket' => getenv('S3_BUCKET') ?: 'autoschedule',
    'region' => getenv('S3_REGION') ?: 'us-east-1',
    'access_key' => getenv('S3_ACCESS_KEY') ?: 'admin',
    'secret_key' => getenv('S3_SECRET_KEY') ?: 'password',
    'public_url' => getenv('S3_PUBLIC_URL') ?: sprintf('%s/%s', rtrim(getenv('S3_ENDPOINT') ?: 'http://minio:9000', '/'), getenv('S3_BUCKET') ?: 'autoschedule'),
    // Onde o upload fica salvo até confirmar sucesso no MinIO
    'temp_path' => getenv('TEMP_STORAGE_PATH') ?: sys_get_temp_dir(),
];
