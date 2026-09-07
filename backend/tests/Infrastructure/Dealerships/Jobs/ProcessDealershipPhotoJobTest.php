<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Dealerships\Jobs;

use App\Domain\Audit\AuditEvent;
use App\Domain\Dealerships\Dealership;
use App\Domain\Dealerships\Ports\DealershipRepository;
use App\Domain\Files\OptimizedImage;
use App\Domain\Files\Ports\FileRepository;
use App\Domain\Files\Ports\ImageOptimizer;
use App\Domain\Files\StoredFile;
use App\Domain\Ports\StorageProvider;
use App\Infrastructure\Dealerships\Jobs\ProcessDealershipPhotoJob;
use App\Infrastructure\Files\FileUploadService;
use App\Infrastructure\Jobs\JobStatusStore;
use App\Infrastructure\Redis\RedisConnection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Domain\Auth\FakeAuditLogger;

/**
 * Unitário com fakes -- o único ponto de integração real é `JobStatusStore`
 * (Redis de verdade, já coberto em `JobStatusStoreTest`); aqui o que importa
 * é a orquestração: otimiza, grava, atualiza a concessionária, reporta progresso.
 */
final class ProcessDealershipPhotoJobTest extends TestCase
{
    private InMemoryDealershipRepository $dealerships;
    private InMemoryFileRepository $files;
    private FakeStorageProvider $storage;
    private FakeAuditLogger $audit;
    private JobStatusStore $jobStatus;
    private string $tempPath;
    private ProcessDealershipPhotoJob $job;
    private string $sourcePath;

    protected function setUp(): void
    {
        $this->tempPath = sys_get_temp_dir() . '/autoschedule-photo-job-test-' . uniqid();
        mkdir($this->tempPath);

        $this->dealerships = new InMemoryDealershipRepository();
        $this->files = new InMemoryFileRepository();
        $this->storage = new FakeStorageProvider();
        $this->audit = new FakeAuditLogger();
        $this->jobStatus = new JobStatusStore(new RedisConnection(
            host: getenv('REDIS_HOST') ?: '127.0.0.1',
            port: (int) (getenv('REDIS_PORT') ?: 6379),
        ));

        $uploads = new FileUploadService($this->storage, $this->files, new FakeImageOptimizer($this->tempPath), $this->tempPath);
        $this->job = new ProcessDealershipPhotoJob($this->dealerships, $this->files, $this->storage, $uploads, $this->audit, $this->jobStatus);

        $this->sourcePath = $this->tempPath . '/upload-source';
        file_put_contents($this->sourcePath, 'conteudo qualquer, o otimizador e fake');
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tempPath . '/*') ?: [] as $leftover) {
            @unlink($leftover);
        }

        @rmdir($this->tempPath);
    }

    #[Test]
    public function processa_com_sucesso_seta_a_foto_e_marca_o_job_como_done(): void
    {
        $dealership = $this->registerFixture();
        $this->dealerships->insert($dealership);
        $this->jobStatus->create('job-1');

        $this->job->handle([
            'job_id' => 'job-1',
            'dealership_id' => $dealership->id,
            'source_path' => $this->sourcePath,
            'original_name' => 'foto.jpg',
            'uploaded_by' => 'user-1',
        ]);

        $updated = $this->dealerships->findById($dealership->id);
        $this->assertNotNull($updated);
        $this->assertNotNull($updated->photoFileId);

        $status = $this->jobStatus->get('job-1');
        $this->assertNotNull($status);
        $this->assertSame('done', $status['status']);
        $this->assertIsArray($status['result']);
        $this->assertArrayHasKey('photo_url', $status['result']);
        $this->assertSame([AuditEvent::DealershipPhotoUpdated], $this->audit->events);
    }

    #[Test]
    public function substitui_a_foto_anterior_e_apaga_o_arquivo_velho_do_storage(): void
    {
        $dealership = $this->registerFixture();
        $this->files->insert(StoredFile::register('old-path', 'old.webp', 'image/webp', 10, 'old-checksum', null));
        $oldFile = $this->files->findByPath('old-path');
        $this->assertNotNull($oldFile);
        $this->dealerships->insert($dealership->withPhoto($oldFile->id));
        $this->jobStatus->create('job-2');

        $this->job->handle([
            'job_id' => 'job-2',
            'dealership_id' => $dealership->id,
            'source_path' => $this->sourcePath,
            'original_name' => 'foto.jpg',
            'uploaded_by' => 'user-1',
        ]);

        $this->assertNull($this->files->findByPath('old-path'), 'foto antiga precisa sumir de `files`, não ficar órfã');
        $this->assertNull($this->storage->contentsOf('old-path'));
    }

    #[Test]
    public function apaga_o_arquivo_temporario_de_origem_mesmo_quando_da_certo(): void
    {
        $dealership = $this->registerFixture();
        $this->dealerships->insert($dealership);
        $this->jobStatus->create('job-3');

        $this->job->handle([
            'job_id' => 'job-3',
            'dealership_id' => $dealership->id,
            'source_path' => $this->sourcePath,
            'original_name' => 'foto.jpg',
            'uploaded_by' => 'user-1',
        ]);

        $this->assertFileDoesNotExist($this->sourcePath);
    }

    #[Test]
    public function concessionaria_inexistente_marca_o_job_como_failed_sem_lancar_excecao(): void
    {
        $this->jobStatus->create('job-4');

        $this->job->handle([
            'job_id' => 'job-4',
            'dealership_id' => 'does-not-exist',
            'source_path' => $this->sourcePath,
            'original_name' => 'foto.jpg',
            'uploaded_by' => 'user-1',
        ]);

        $status = $this->jobStatus->get('job-4');
        $this->assertNotNull($status);
        $this->assertSame('failed', $status['status']);
        $this->assertFileDoesNotExist($this->sourcePath);
    }

    private function registerFixture(): Dealership
    {
        return Dealership::register(
            ownerUserId: 'owner-1',
            name: 'Auto Center',
            zipCode: '01000-000',
            address: 'Rua Antiga',
            number: '10',
            complement: null,
            neighborhood: 'Bairro',
            city: 'Cidade',
            state: 'SP',
            phone: '11988888888',
        );
    }
}

final class InMemoryDealershipRepository implements DealershipRepository
{
    /** @var array<string, Dealership> */
    private array $dealerships = [];

    public function findById(string $id): ?Dealership
    {
        return $this->dealerships[$id] ?? null;
    }

    public function insert(Dealership $dealership): void
    {
        $this->dealerships[$dealership->id] = $dealership;
    }

    public function update(Dealership $dealership): void
    {
        $this->dealerships[$dealership->id] = $dealership;
    }

    public function findByOwner(string $ownerUserId, int $limit, int $offset): array
    {
        return [];
    }

    public function countByOwner(string $ownerUserId): int
    {
        return 0;
    }

    public function findPage(int $limit, int $offset): array
    {
        return [];
    }

    public function count(): int
    {
        return 0;
    }

    public function trash(string $id, bool $byOwnerDeactivation): void
    {
    }

    public function restore(string $id): void
    {
    }

    public function findPurgeEligible(int $graceDays, \DateTimeImmutable $now): array
    {
        return [];
    }

    public function trashAllOwnedBy(string $ownerUserId): void
    {
    }

    public function restoreAutoTrashedOwnedBy(string $ownerUserId): void
    {
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
}

final class FakeStorageProvider implements StorageProvider
{
    /** @var array<string, string> */
    private array $objects = [];

    public function put(string $path, string $contents, string $mimeType): void
    {
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

/** Não decodifica nada de verdade -- só prova a orquestração do job. GD de verdade é testado em `GdImageOptimizerTest`. */
final readonly class FakeImageOptimizer implements ImageOptimizer
{
    public function __construct(private string $tempPath)
    {
    }

    public function optimizeToWebp(string $sourcePath): OptimizedImage
    {
        $path = sprintf('%s/%s.webp', rtrim($this->tempPath, '/'), uniqid('fake-optimized-', true));
        file_put_contents($path, 'RIFF' . pack('V', 12) . 'WEBP');

        return new OptimizedImage($path, 100, 100);
    }
}
