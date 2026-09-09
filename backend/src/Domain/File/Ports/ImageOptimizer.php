<?php

declare(strict_types=1);

namespace App\Domain\File\Ports;

use App\Domain\File\OptimizedImage;

/** Todo upload de foto passa por aqui antes do storage: o formato do site é WebP, não o que o cliente mandou. */
interface ImageOptimizer
{
    /**
     * Recebe o conteúdo já lido (não um caminho) -- quem chama pode estar num processo
     * diferente de quem gravou o arquivo original (worker vs backend, sem disco em comum).
     *
     * @return OptimizedImage caminho de um arquivo temporário -- quem chama apaga depois de usar.
     */
    public function optimizeToWebp(string $contents): OptimizedImage;
}
