<?php

declare(strict_types=1);

namespace App\Infrastructure\Logging;

use Psr\Log\AbstractLogger;

final class Logger extends AbstractLogger
{
    /** @param resource|null $stream padrão é php://stderr, mantido aberto pela vida toda do logger */
    public function __construct(private $stream = null)
    {
        $this->stream ??= fopen('php://stderr', 'a');
    }

    /** @param array<string, mixed> $context */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $line = json_encode([
            'timestamp' => date(DATE_ATOM),
            'level' => (string) $level,
            'message' => (string) $message,
            'context' => $context,
        ], JSON_UNESCAPED_SLASHES);

        fwrite($this->stream, $line . PHP_EOL);
    }
}
