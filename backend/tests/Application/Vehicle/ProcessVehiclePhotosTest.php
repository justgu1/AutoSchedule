<?php

declare(strict_types=1);

namespace Tests\Application\Vehicle;

use App\Application\File\MaterializeStagedFile;
use App\Application\File\UploadFile;
use App\Application\Vehicle\ProcessVehiclePhotos;
use App\Domain\Audit\AuditEvent;
use App\Domain\File\OptimizedImage;
use App\Domain\File\Ports\ImageOptimizer;
use App\Domain\Shared\Money;
use App\Domain\Vehicle\Vehicle;
use App\Infrastructure\File\LocalTempFileStore;
use App\Infrastructure\Jobs\JobStatusStore;
use App\Infrastructure\Redis\RedisConnection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\DirectTransaction;
use Tests\Support\FakeAuditLogger;
use Tests\Support\InMemoryFileRepository;
use Tests\Support\InMemoryVehicleImageRepository;
use Tests\Support\InMemoryVehicleRepository;

/** O que importa aqui é a orquestração do lote; o único ponto real é o `JobStatusStore`. */
#[Group('integration')]
final class ProcessVehiclePhotosTest extends TestCase
{
    private InMemoryVehicleRepository $vehicles;
    private InMemoryVehicleImageRepository $images;
    private FakeAuditLogger $audit;
    private JobStatusStore $jobStatus;
    private string $tempPath;
    private ProcessVehiclePhotos $processPhotos;
    private Vehicle $vehicle;

    protected function setUp(): void
    {
        $this->tempPath = sys_get_temp_dir() . '/autoschedule-vehicle-photos-test-' . uniqid();
        mkdir($this->tempPath);

        $this->vehicles = new InMemoryVehicleRepository();
        $this->images = new InMemoryVehicleImageRepository();
        $this->audit = new FakeAuditLogger();
        $this->jobStatus = new JobStatusStore(new RedisConnection(
            host: getenv('REDIS_HOST') ?: '127.0.0.1',
            port: (int) (getenv('REDIS_PORT') ?: 6379),
        ));

        $tempFiles = new LocalTempFileStore($this->tempPath);
        $this->processPhotos = new ProcessVehiclePhotos(
            $this->vehicles,
            $this->images,
            new UploadFile(new NullStorageProvider(), new InMemoryFileRepository(), new StubImageOptimizer($this->tempPath), $tempFiles),
            $this->audit,
            $this->jobStatus,
            $tempFiles,
            new MaterializeStagedFile($tempFiles),
            new DirectTransaction(),
        );

        $this->vehicle = Vehicle::register('dealership-1', 'Chevrolet', 'Onix', null, 2023, 2023, new Money(8990000));
        $this->vehicles->insert($this->vehicle);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tempPath . '/*') ?: [] as $file) {
            unlink($file);
        }

        rmdir($this->tempPath);
    }

    #[Test]
    public function processa_o_lote_inteiro_e_marca_o_job_como_done(): void
    {
        $jobId = 'job-' . uniqid();

        ($this->processPhotos)($jobId, $this->vehicle->id, $this->sources(3), 'user-1');

        $this->assertCount(3, $this->images->findByVehicle($this->vehicle->id));
        $this->assertSame('done', $this->jobStatus->get($jobId)['status'] ?? null);
        $this->assertSame([AuditEvent::VehicleImagesAdded], $this->audit->events);
    }

    #[Test]
    public function posicoes_sao_atribuidas_em_sequencia_a_partir_do_fim_da_galeria(): void
    {
        ($this->processPhotos)('job-' . uniqid(), $this->vehicle->id, $this->sources(2), null);
        ($this->processPhotos)('job-' . uniqid(), $this->vehicle->id, $this->sources(2), null);

        $positions = array_map(
            static fn (object $image): int => $image->position,
            $this->images->findByVehicle($this->vehicle->id),
        );
        $this->assertSame([0, 1, 2, 3], $positions);
    }

    #[Test]
    public function veiculo_inexistente_marca_o_job_como_failed_sem_lancar_excecao(): void
    {
        $jobId = 'job-' . uniqid();

        ($this->processPhotos)($jobId, 'nao-existe', $this->sources(1), null);

        $this->assertSame('failed', $this->jobStatus->get($jobId)['status'] ?? null);
        $this->assertSame([], $this->audit->events);
    }

    /** Sem o `finally`, um lote que falha no meio deixaria o backup local pra trás. */
    #[Test]
    public function descarta_os_arquivos_temporarios_mesmo_quando_falha(): void
    {
        $sources = $this->sources(2);

        ($this->processPhotos)('job-' . uniqid(), 'nao-existe', $sources, null);

        foreach ($sources as $source) {
            $this->assertFileDoesNotExist($source['source_path']);
        }
    }

    /** @return list<array{source_path: string, original_name: string}> */
    private function sources(int $count): array
    {
        $sources = [];

        for ($i = 0; $i < $count; ++$i) {
            $path = sprintf('%s/source-%s', $this->tempPath, uniqid());
            file_put_contents($path, 'conteudo ' . uniqid());
            $sources[] = ['source_path' => $path, 'original_name' => "foto-{$i}.jpg"];
        }

        return $sources;
    }
}

/** Não decodifica nada de verdade -- só prova a orquestração do lote. GD de verdade é testado em `GdImageOptimizerTest`. */
final readonly class StubImageOptimizer implements ImageOptimizer
{
    public function __construct(private string $tempPath)
    {
    }

    public function optimizeToWebp(string $sourcePath): OptimizedImage
    {
        $path = sprintf('%s/%s.webp', rtrim($this->tempPath, '/'), uniqid('optimized-', true));
        file_put_contents($path, 'RIFF' . pack('V', 12) . 'WEBP' . uniqid());

        return new OptimizedImage($path, 100, 100);
    }
}

final class NullStorageProvider implements \App\Domain\File\Ports\StorageProvider
{
    public function put(string $path, string $contents, string $mimeType): void
    {
    }

    public function get(string $path): string
    {
        return '';
    }

    public function url(string $path): string
    {
        return 'https://storage.test/' . $path;
    }

    public function delete(string $path): void
    {
    }
}
