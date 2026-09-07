<?php

declare(strict_types=1);

namespace App\Infrastructure\Files;

use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Domain\Files\OptimizedImage;
use App\Domain\Files\Ports\ImageOptimizer;
use App\Domain\Support\Uuid;

final readonly class GdImageOptimizer implements ImageOptimizer
{
    private const int MAX_DIMENSION_PX = 1600;
    private const int WEBP_QUALITY = 82;

    public function __construct(private string $tempPath)
    {
    }

    public function optimizeToWebp(string $sourcePath): OptimizedImage
    {
        $contents = file_get_contents($sourcePath);
        $source = $contents === false ? false : @imagecreatefromstring($contents);

        if (!$source instanceof \GdImage) {
            throw new DomainException('Uploaded file is not a valid image.', DomainErrorType::Validation, ['image' => 'The file could not be decoded as an image.']);
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1.0, self::MAX_DIMENSION_PX / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        $resized = imagecreatetruecolor($targetWidth, $targetHeight);
        // Preserva transparência (PNG de origem) em vez de virar preto sólido no WebP.
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagecopyresampled($resized, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        imagedestroy($source);

        $path = sprintf('%s/%s.webp', rtrim($this->tempPath, '/'), Uuid::v7());
        imagewebp($resized, $path, self::WEBP_QUALITY);
        imagedestroy($resized);

        return new OptimizedImage($path, $targetWidth, $targetHeight);
    }
}
