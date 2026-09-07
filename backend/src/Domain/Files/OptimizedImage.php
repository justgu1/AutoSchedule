<?php

declare(strict_types=1);

namespace App\Domain\Files;

/** Resultado de otimizar uma imagem pro padrão do site -- WebP, redimensionada, pronta pro CDN. */
final readonly class OptimizedImage
{
    public function __construct(
        public string $path,
        public int $width,
        public int $height,
    ) {
    }
}
