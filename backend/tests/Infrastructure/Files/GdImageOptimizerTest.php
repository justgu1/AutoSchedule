<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Files;

use App\Domain\Exceptions\DomainException;
use App\Infrastructure\Files\GdImageOptimizer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GdImageOptimizerTest extends TestCase
{
    private string $tempPath;
    private GdImageOptimizer $optimizer;

    /** @var list<string> */
    private array $createdFiles = [];

    protected function setUp(): void
    {
        $this->tempPath = sys_get_temp_dir() . '/autoschedule-image-optimizer-test-' . uniqid();
        mkdir($this->tempPath);
        $this->optimizer = new GdImageOptimizer($this->tempPath);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tempPath . '/*') ?: [] as $leftover) {
            unlink($leftover);
        }

        rmdir($this->tempPath);

        foreach ($this->createdFiles as $file) {
            @unlink($file);
        }
    }

    #[Test]
    public function converte_uma_imagem_pequena_pra_webp_sem_redimensionar(): void
    {
        $source = $this->createPngFixture(200, 100);

        $optimized = $this->optimizer->optimizeToWebp($source);

        $this->assertFileExists($optimized->path);
        $this->assertSame(200, $optimized->width);
        $this->assertSame(100, $optimized->height);
        $this->assertSame('image/webp', $this->detectMimeType($optimized->path));
    }

    #[Test]
    public function redimensiona_uma_imagem_grande_preservando_a_proporcao(): void
    {
        $source = $this->createPngFixture(3200, 1600);

        $optimized = $this->optimizer->optimizeToWebp($source);

        $this->assertSame(1600, $optimized->width);
        $this->assertSame(800, $optimized->height);
    }

    #[Test]
    public function grava_o_resultado_dentro_do_tempPath_configurado(): void
    {
        $source = $this->createPngFixture(10, 10);

        $optimized = $this->optimizer->optimizeToWebp($source);

        $this->assertStringStartsWith(rtrim($this->tempPath, '/') . '/', $optimized->path);
        $this->assertStringEndsWith('.webp', $optimized->path);
    }

    #[Test]
    public function rejeita_um_arquivo_que_nao_e_uma_imagem_de_verdade(): void
    {
        $source = $this->tempFilePath('not-an-image-');
        file_put_contents($source, 'isso aqui nao e imagem nenhuma');

        $this->expectException(DomainException::class);

        $this->optimizer->optimizeToWebp($source);
    }

    /**
     * @param positive-int $width
     * @param positive-int $height
     */
    private function createPngFixture(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        $color = imagecolorallocate($image, 10, 20, 30);
        $this->assertNotFalse($color);
        imagefill($image, 0, 0, $color);

        $path = $this->tempFilePath('optimizer-source-') . '.png';
        imagepng($image, $path);
        imagedestroy($image);

        return $path;
    }

    private function detectMimeType(string $path): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $this->assertNotFalse($finfo);
        $mimeType = finfo_file($finfo, $path);
        finfo_close($finfo);

        return $mimeType !== false ? $mimeType : 'application/octet-stream';
    }

    private function tempFilePath(string $prefix): string
    {
        $path = tempnam(sys_get_temp_dir(), $prefix);
        $this->assertNotFalse($path);
        $this->createdFiles[] = $path;

        return $path;
    }
}
