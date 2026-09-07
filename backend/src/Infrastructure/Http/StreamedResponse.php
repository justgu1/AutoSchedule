<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

/**
 * Resposta de corpo longo/incremental (SSE) -- `send()` não faz um `echo`
 * só, ele entrega o controle pra quem criou a resposta escrever aos poucos
 * e dar `flush()` a cada pedaço. Sem cookies: SSE não usa.
 */
final class StreamedResponse extends Response
{
    /** @param \Closure(): void $stream @param array<string, string> $headers */
    public function __construct(private readonly \Closure $stream, array $headers = [])
    {
        parent::__construct(body: '', status: 200, headers: $headers);
    }

    public function send(): void
    {
        http_response_code($this->status());

        foreach ($this->headers() as $name => $value) {
            header($name . ': ' . $value);
        }

        // Sem isso o PHP-FPM guarda tudo num buffer e só entrega no final da
        // request -- o cliente veria a stream inteira de uma vez, não "ao vivo".
        while (ob_get_level() > 0) {
            ob_end_flush();
        }
        ob_implicit_flush(true);

        ($this->stream)();
    }
}
