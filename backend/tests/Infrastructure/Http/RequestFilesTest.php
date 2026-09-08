<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Http;

use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Cobre a normalização de `$_FILES`, que o PHP entrega em formatos diferentes conforme
 * o campo seja `image` ou `images[]`.
 */
final class RequestFilesTest extends TestCase
{
    #[Test]
    public function campo_simples_vira_lista_de_um_e_file_devolve_esse_arquivo(): void
    {
        $_FILES = [
            'image' => ['tmp_name' => '/tmp/a', 'name' => 'a.jpg', 'size' => 10, 'error' => UPLOAD_ERR_OK],
        ];

        $request = Request::fromGlobals('');

        $this->assertCount(1, $request->files('image'));
        $this->assertSame('/tmp/a', $request->file('image')?->tmpName);
    }

    #[Test]
    public function campo_multiplo_vira_um_uploaded_file_por_arquivo_enviado(): void
    {
        $_FILES = [
            'images' => [
                'tmp_name' => ['/tmp/a', '/tmp/b'],
                'name' => ['a.jpg', 'b.png'],
                'size' => [10, 20],
                'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_OK],
            ],
        ];

        $files = Request::fromGlobals('')->files('images');

        $this->assertSame(
            [['/tmp/a', 'a.jpg', 10], ['/tmp/b', 'b.png', 20]],
            array_map(static fn (UploadedFile $f): array => [$f->tmpName, $f->originalName, $f->size], $files),
        );
    }

    #[Test]
    public function file_devolve_o_primeiro_arquivo_quando_o_campo_e_multiplo(): void
    {
        $_FILES = [
            'images' => [
                'tmp_name' => ['/tmp/a', '/tmp/b'],
                'name' => ['a.jpg', 'b.png'],
                'size' => [10, 20],
                'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_OK],
            ],
        ];

        $this->assertSame('/tmp/a', Request::fromGlobals('')->file('images')?->tmpName);
    }

    #[Test]
    public function campo_ausente_devolve_lista_vazia(): void
    {
        $_FILES = [];

        $this->assertSame([], Request::fromGlobals('')->files('images'));
    }

    protected function tearDown(): void
    {
        $_FILES = [];
    }
}
