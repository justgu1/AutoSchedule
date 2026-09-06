<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Storage;

use App\Infrastructure\Storage\MinioAdapter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** Teste de integração: grava/lê/apaga um objeto real no MinIO do docker-compose. */
final class MinioAdapterTest extends TestCase
{
    private MinioAdapter $adapter;

    protected function setUp(): void
    {
        $this->adapter = new MinioAdapter(
            endpoint: getenv('S3_ENDPOINT') ?: 'http://minio:9000',
            bucket: getenv('S3_BUCKET') ?: 'autoschedule',
            region: getenv('S3_REGION') ?: 'us-east-1',
            accessKey: getenv('S3_ACCESS_KEY') ?: 'admin',
            secretKey: getenv('S3_SECRET_KEY') ?: 'password',
            publicUrl: sprintf('%s/%s', rtrim(getenv('S3_ENDPOINT') ?: 'http://minio:9000', '/'), getenv('S3_BUCKET') ?: 'autoschedule'),
        );
    }

    #[Test]
    public function put_grava_o_objeto_e_url_aponta_pra_ele(): void
    {
        $path = 'tests/' . uniqid('minio-adapter-', true) . '.txt';

        $this->adapter->put($path, 'conteúdo de teste', 'text/plain');

        $contents = file_get_contents($this->adapter->url($path));

        $this->assertSame('conteúdo de teste', $contents);

        $this->adapter->delete($path);
    }

    #[Test]
    public function delete_remove_o_objeto(): void
    {
        $path = 'tests/' . uniqid('minio-adapter-delete-', true) . '.txt';
        $this->adapter->put($path, 'apagar depois', 'text/plain');

        $this->adapter->delete($path);

        $this->assertFalse(@file_get_contents($this->adapter->url($path)));
    }
}
