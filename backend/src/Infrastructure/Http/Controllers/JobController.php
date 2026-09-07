<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\StreamedResponse;
use App\Infrastructure\Jobs\JobStatusStore;

/**
 * Genérico de propósito -- não sabe o que o job faz, só expõe o progresso que
 * ele mesmo reportou em `JobStatusStore`. Mesmo endpoint serve qualquer job
 * assíncrono futuro (ex: import em lote de fotos de veículo), não só foto de
 * concessionária.
 *
 * Autorização aqui é só "logado" -- o id do job (UUID) não é adivinhável e
 * não carrega dado sensível além do que o próprio dono já pediu pra processar.
 */
final readonly class JobController
{
    private const int MAX_STREAM_SECONDS = 55;
    private const int POLL_INTERVAL_MICROSECONDS = 400_000;

    public function __construct(private JobStatusStore $jobStatus)
    {
    }

    public function show(Request $request): Response
    {
        return Response::success($this->requireStatus($request));
    }

    /**
     * SSE -- `EventSource` do browser reconecta sozinho se a conexão cair ou
     * `MAX_STREAM_SECONDS` esgotar antes do job terminar; um novo `GET`
     * pega o progresso de onde parou (o estado vive no Redis, não na conexão).
     */
    public function events(Request $request): Response
    {
        // Falha rápido aqui (404 JSON normal) se o job nem existe -- só a
        // partir daqui é que vale a pena virar text/event-stream.
        $this->requireStatus($request);
        $jobId = (string) $request->param('id');

        return new StreamedResponse(
            fn () => $this->stream($jobId),
            [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'Connection' => 'keep-alive',
                'X-Accel-Buffering' => 'no',
            ],
        );
    }

    private function stream(string $jobId): void
    {
        $lastPayload = null;
        $deadline = microtime(true) + self::MAX_STREAM_SECONDS;

        while (microtime(true) < $deadline) {
            if (connection_aborted() === 1) {
                return;
            }

            $status = $this->jobStatus->get($jobId);

            if ($status !== null) {
                $payload = json_encode($status, JSON_THROW_ON_ERROR);

                if ($payload !== $lastPayload) {
                    echo "event: progress\n";
                    echo "data: {$payload}\n\n";
                    $lastPayload = $payload;
                }

                if (in_array($status['status'], ['done', 'failed'], true)) {
                    return;
                }
            }

            usleep(self::POLL_INTERVAL_MICROSECONDS);
        }
    }

    /** @return array<string, mixed> */
    private function requireStatus(Request $request): array
    {
        $jobId = $request->param('id');
        $status = $jobId !== null ? $this->jobStatus->get($jobId) : null;

        if ($status === null) {
            throw new DomainException('Job not found.', DomainErrorType::NotFound);
        }

        return $status;
    }
}
