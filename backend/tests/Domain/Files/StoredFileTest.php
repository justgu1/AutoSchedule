<?php

declare(strict_types=1);

namespace Tests\Domain\Files;

use App\Domain\Files\StoredFile;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StoredFileTest extends TestCase
{
    #[Test]
    public function register_monta_um_arquivo_novo_com_os_dados_informados(): void
    {
        $file = StoredFile::register(
            path: 'ab/abcdef',
            originalName: 'foto.jpg',
            mimeType: 'image/jpeg',
            sizeBytes: 1234,
            checksum: 'abcdef',
            uploadedBy: 'user-1',
        );

        $this->assertNotSame('', $file->id);
        $this->assertSame('ab/abcdef', $file->path);
        $this->assertSame('foto.jpg', $file->originalName);
        $this->assertSame('image/jpeg', $file->mimeType);
        $this->assertSame(1234, $file->sizeBytes);
        $this->assertSame('abcdef', $file->checksum);
        $this->assertSame('user-1', $file->uploadedBy);
    }

    #[Test]
    public function register_aceita_upload_sem_usuario_associado(): void
    {
        $file = StoredFile::register('ab/abcdef', 'foto.jpg', 'image/jpeg', 1234, 'abcdef', uploadedBy: null);

        $this->assertNull($file->uploadedBy);
    }
}
