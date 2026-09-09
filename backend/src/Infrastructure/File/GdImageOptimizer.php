<?php

declare(strict_types=1);

namespace App\Infrastructure\File;

use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Domain\File\OptimizedImage;
use App\Domain\File\Ports\ImageOptimizer;
use App\Domain\Shared\Uuid;

final readonly class GdImageOptimizer implements ImageOptimizer
{
    private const int MAX_DIMENSION_PX = 1600;
    private const int WEBP_QUALITY = 82;

    public function __construct(private string $tempPath)
    {
    }

    public function optimizeToWebp(string $contents): OptimizedImage
    {
        $source = @imagecreatefromstring($contents);

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

        $path = sprintf('%s/%s.webp', rtrim($this->tempPath, '/'), Uuid::v7());
        imagewebp($resized, $path, self::WEBP_QUALITY);

        return new OptimizedImage($path, $targetWidth, $targetHeight);
    }
}
