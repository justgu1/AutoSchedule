<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Domain\File\Ports\StorageProvider;
use Aws\S3\S3Client;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;

final readonly class MinioAdapter implements StorageProvider
{
    private FilesystemOperator $filesystem;

    /**
     * @param string $endpoint Endpoint S3-compatível do MinIO, ex: `http://minio:9000`.
     * @param string $publicUrl Base da URL pública do objeto; o bucket precisa de policy de leitura pública nesse prefixo.
     */
    public function __construct(
        string $endpoint,
        string $bucket,
        string $region,
        string $accessKey,
        string $secretKey,
        private string $publicUrl,
    ) {
        $client = new S3Client([
            'endpoint' => $endpoint,
            'region' => $region,
            'version' => 'latest',
            'use_path_style_endpoint' => true,
            'credentials' => [
                'key' => $accessKey,
                'secret' => $secretKey,
            ],
        ]);

        $this->filesystem = new Filesystem(new AwsS3V3Adapter($client, $bucket));
    }

    public function put(string $path, string $contents, string $mimeType): void
    {
        $this->filesystem->write($path, $contents, ['mimetype' => $mimeType]);
    }

    public function get(string $path): string
    {
        return $this->filesystem->read($path);
    }

    public function url(string $path): string
    {
        return rtrim($this->publicUrl, '/') . '/' . ltrim($path, '/');
    }

    public function delete(string $path): void
    {
        $this->filesystem->delete($path);
    }
}
