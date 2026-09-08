<?php

declare(strict_types=1);

namespace App\Application\Shared;

/** O limite vale pra qualquer imagem que o site aceita, então mora num lugar só. */
final class UploadLimits
{
    public const int MAX_IMAGE_BYTES = 20 * 1024 * 1024;
}
