<?php

declare(strict_types=1);

namespace App\Domain\Files\Ports;

use App\Domain\Files\OptimizedImage;

/**
 * Converte qualquer imagem enviada pro padrão do site: WebP, redimensionada,
 * já pronta pra servir via CDN. Todo upload de foto (concessionária, e no
 * futuro veículo) passa por aqui antes de ir pro storage.
 */
interface ImageOptimizer
{
    /** @return OptimizedImage caminho de um arquivo temporário -- quem chama apaga depois de usar. */
    public function optimizeToWebp(string $sourcePath): OptimizedImage;
}
