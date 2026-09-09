<?php

declare(strict_types=1);

namespace Tests\Infrastructure\File;

use App\Domain\File\Ports\StorageProvider;
use App\Infrastructure\File\S3TempFileStore;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class S3TempFileStoreTest extends TestCase
{
    private InMemoryStorageProvider $storage;
    private S3TempFileStore $tempFiles;

    protected function setUp(): void
    {
        $this->storage = new InMemoryStorageProvider();
        $this->tempFiles = new S3TempFileStore($this->storage);
    }

    #[Test]
    public function stage_grava_no_storage_e_devolve_uma_chave_lida_de_volta_com_o_mesmo_conteudo(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'source-');
        file_put_contents($source, 'conteudo de teste');

        $path = $this->tempFiles->stage($source, 'a-key');

        $this->assertSame('conteudo de teste', $this->tempFiles->read($path));
        unlink($source);
    }

    #[Test]
    public function stage_isola_sob_um_prefixo_pra_nao_colidir_com_o_arquivo_final_de_mesmo_nome(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'source-');
        file_put_contents($source, 'x');

        $path = $this->tempFiles->stage($source, 'shared-name');

        $this->assertNotSame('shared-name', $path);
        $this->assertStringContainsString('shared-name', $path);
        unlink($source);
    }

    #[Test]
    public function detect_mime_type_reconhece_pelo_conteudo_sem_precisar_de_arquivo_local(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'source-');
        // PNG 1x1 real (base64) -- cabeçalho de brinquedo não basta pro `finfo` reconhecer.
        file_put_contents($source, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
        ));

        $path = $this->tempFiles->stage($source, 'fake.png');

        $this->assertSame('image/png', $this->tempFiles->detectMimeType($path));
        unlink($source);
    }

    #[Test]
    public function discard_remove_do_storage(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'source-');
        file_put_contents($source, 'x');
        $path = $this->tempFiles->stage($source, 'to-remove');

        $this->tempFiles->discard($path);

        $this->assertFalse($this->storage->has($path));
        unlink($source);
    }
}

final class InMemoryStorageProvider implements StorageProvider
{
    /** @var array<string, string> */
    private array $objects = [];

    public function put(string $path, string $contents, string $mimeType): void
    {
        $this->objects[$path] = $contents;
    }

    public function get(string $path): string
    {
        return $this->objects[$path] ?? '';
    }

    public function url(string $path): string
    {
        return 'https://fake-storage.test/' . $path;
    }

    public function delete(string $path): void
    {
        unset($this->objects[$path]);
    }

    public function has(string $path): bool
    {
        return array_key_exists($path, $this->objects);
    }
}
