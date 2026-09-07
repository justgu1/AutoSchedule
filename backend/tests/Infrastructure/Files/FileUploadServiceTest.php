<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Files;

use App\Domain\Exceptions\DomainException;
use App\Domain\Files\OptimizedImage;
use App\Domain\Files\Ports\FileRepository;
use App\Domain\Files\Ports\ImageOptimizer;
use App\Domain\Files\StoredFile;
use App\Domain\Ports\StorageProvider;
use App\Infrastructure\Files\FileUploadService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FileUploadServiceTest extends TestCase
{
    private string $tempPath;
    private InMemoryFileRepository $files;
    private FakeStorageProvider $storage;
    private FakeImageOptimizer $imageOptimizer;
    private FileUploadService $service;

    protected function setUp(): void
    {
        $this->tempPath = sys_get_temp_dir() . '/autoschedule-upload-test-' . uniqid();
        mkdir($this->tempPath);

        $this->files = new InMemoryFileRepository();
        $this->storage = new FakeStorageProvider();
        $this->imageOptimizer = new FakeImageOptimizer($this->tempPath);
        $this->service = new FileUploadService($this->storage, $this->files, $this->imageOptimizer, $this->tempPath);
    }

    protected function tearDown(): void
    {
        foreach ($this->tempPathContents() as $leftover) {
            unlink($leftover);
        }

        rmdir($this->tempPath);
    }

    #[Test]
    public function upload_grava_o_arquivo_e_remove_o_backup_local_quando_tudo_da_certo(): void
    {
        $uploadedTmpPath = $this->createUpload('hello world');

        $file = $this->service->upload($uploadedTmpPath, 'greeting.txt', ['text/plain'], uploadedBy: 'user-1');

        $this->assertSame('text/plain', $file->mimeType);
        $this->assertSame('user-1', $file->uploadedBy);
        $this->assertSame($file, $this->files->findById($file->id));
        $this->assertSame('hello world', $this->storage->contentsOf($file->path));
        $this->assertSame([], $this->tempPathContents(), 'backup local deve sumir depois do put()+insert() confirmados');
    }

    #[Test]
    public function upload_rejeita_mime_type_nao_permitido_e_remove_o_backup_local(): void
    {
        $uploadedTmpPath = $this->createUpload('hello world');

        try {
            $this->service->upload($uploadedTmpPath, 'greeting.txt', ['image/png'], uploadedBy: null);
            $this->fail('Expected a DomainException for a disallowed MIME type.');
        } catch (DomainException $exception) {
            $this->assertSame('text/plain', $this->extractRejectedMimeType($exception->getMessage()));
        }

        $this->assertSame([], $this->tempPathContents(), 'conteúdo rejeitado nunca passaria num retry, sem motivo pra guardar backup');
        $this->assertSame(0, $this->storage->putCount);
    }

    #[Test]
    public function upload_reaproveita_arquivo_ja_existente_pelo_mesmo_conteudo(): void
    {
        $first = $this->service->upload($this->createUpload('same content'), 'a.txt', ['text/plain'], uploadedBy: null);
        $second = $this->service->upload($this->createUpload('same content'), 'b.txt', ['text/plain'], uploadedBy: null);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, $this->storage->putCount);
    }

    #[Test]
    public function upload_mantem_o_backup_local_quando_o_storage_falha(): void
    {
        $this->storage->failNextPut = true;
        $uploadedTmpPath = $this->createUpload('hello world');

        try {
            $this->service->upload($uploadedTmpPath, 'greeting.txt', ['text/plain'], uploadedBy: null);
            $this->fail('Expected the storage failure to propagate.');
        } catch (\RuntimeException) {
            // esperado
        }

        $backups = $this->tempPathContents();
        $this->assertCount(1, $backups, 'backup local precisa sobreviver a uma falha do storage, pra retry manual');
        $this->assertSame('hello world', file_get_contents($backups[0]));
        $this->assertSame([], $this->files->all());
    }

    #[Test]
    public function upload_image_otimiza_antes_de_gravar_e_grava_como_webp(): void
    {
        $uploadedTmpPath = $this->createUpload('not really a jpeg, the optimizer is faked');

        $file = $this->service->uploadImage($uploadedTmpPath, 'foto.jpg', uploadedBy: 'user-1');

        $this->assertSame('image/webp', $file->mimeType);
        $this->assertSame(1, $this->imageOptimizer->calls);
    }

    #[Test]
    public function upload_image_apaga_o_temporario_do_otimizador_mesmo_quando_falha(): void
    {
        $this->storage->failNextPut = true;
        $uploadedTmpPath = $this->createUpload('not really a jpeg, the optimizer is faked');

        try {
            $this->service->uploadImage($uploadedTmpPath, 'foto.jpg', uploadedBy: null);
            $this->fail('Expected the storage failure to propagate.');
        } catch (\RuntimeException) {
            // esperado
        }

        $this->assertSame([], glob($this->tempPath . '/*.webp') ?: [], 'temporário do otimizador não pode sobrar, mesmo em falha');
    }

    private function createUpload(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'upload-source-');
        file_put_contents($path, $contents);

        return $path;
    }

    /** @return list<string> */
    private function tempPathContents(): array
    {
        return glob($this->tempPath . '/*') ?: [];
    }

    private function extractRejectedMimeType(string $message): string
    {
        if (preg_match('/"([^"]+)"/', $message, $matches) !== 1) {
            $this->fail('Expected the exception message to quote the rejected MIME type.');
        }

        return $matches[1];
    }
}

final class InMemoryFileRepository implements FileRepository
{
    /** @var array<string, StoredFile> */
    private array $files = [];

    public function findById(string $id): ?StoredFile
    {
        return $this->files[$id] ?? null;
    }

    public function findByPath(string $path): ?StoredFile
    {
        foreach ($this->files as $file) {
            if ($file->path === $path) {
                return $file;
            }
        }

        return null;
    }

    public function insert(StoredFile $file): void
    {
        $this->files[$file->id] = $file;
    }

    public function delete(string $id): void
    {
        unset($this->files[$id]);
    }

    /** @return list<StoredFile> */
    public function all(): array
    {
        return array_values($this->files);
    }
}

/** Não decodifica nada de verdade -- só prova que `uploadImage()` chama o otimizador e usa o resultado dele. GD de verdade é testado em `GdImageOptimizerTest`. */
final class FakeImageOptimizer implements ImageOptimizer
{
    public int $calls = 0;

    public function __construct(private readonly string $tempPath)
    {
    }

    public function optimizeToWebp(string $sourcePath): OptimizedImage
    {
        ++$this->calls;

        $path = sprintf('%s/%s.webp', rtrim($this->tempPath, '/'), uniqid('fake-optimized-', true));
        // Cabeçalho RIFF/WEBP mínimo -- o suficiente pro `finfo` real (usado por `FileUploadService::detectRealMimeType()`) reconhecer como `image/webp` sem precisar de um payload VP8 válido.
        file_put_contents($path, 'RIFF' . pack('V', 12) . 'WEBP');

        return new OptimizedImage($path, 100, 100);
    }
}

final class FakeStorageProvider implements StorageProvider
{
    public int $putCount = 0;
    public bool $failNextPut = false;

    /** @var array<string, string> */
    private array $objects = [];

    public function put(string $path, string $contents, string $mimeType): void
    {
        if ($this->failNextPut) {
            $this->failNextPut = false;

            throw new \RuntimeException('Simulated storage outage.');
        }

        ++$this->putCount;
        $this->objects[$path] = $contents;
    }

    public function url(string $path): string
    {
        return 'https://fake-storage.test/' . $path;
    }

    public function delete(string $path): void
    {
        unset($this->objects[$path]);
    }

    public function contentsOf(string $path): ?string
    {
        return $this->objects[$path] ?? null;
    }
}
