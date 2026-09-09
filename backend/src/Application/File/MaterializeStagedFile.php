<?php

declare(strict_types=1);

namespace App\Application\File;

use App\Application\Ports\TempFileStore;

/**
 * `UploadFile::uploadImage()` só aceita caminho local -- jobs do worker recebem uma chave
 * staged (pode ter vindo de outro pod), materializam pra um arquivo local antes de chamar.
 */
final readonly class MaterializeStagedFile
{
    public function __construct(private TempFileStore $tempFiles)
    {
    }

    /** @return string caminho local; quem chama apaga depois de usar. */
    public function __invoke(string $stagedPath): string
    {
        $localPath = tempnam(sys_get_temp_dir(), 'staged-');
        \assert($localPath !== false);
        file_put_contents($localPath, $this->tempFiles->read($stagedPath));

        return $localPath;
    }
}
