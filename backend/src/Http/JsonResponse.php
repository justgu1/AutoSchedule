<?php

declare(strict_types=1);

namespace App\Http;

final class JsonResponse extends Response
{
    /**
     * @param array<string, string> $headers
     * @throws \JsonException quando $data não pode ser serializado
     */
    public function __construct(mixed $data, int $status = 200, array $headers = [])
    {
        $headers['Content-Type'] = 'application/json';

        parent::__construct(
            body: json_encode($data, JSON_THROW_ON_ERROR),
            status: $status,
            headers: $headers,
        );
    }
}
