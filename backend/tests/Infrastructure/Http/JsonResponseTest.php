<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Http;

use App\Infrastructure\Http\JsonResponse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class JsonResponseTest extends TestCase
{
    #[Test]
    public function serializa_os_dados_e_define_o_content_type(): void
    {
        $response = new JsonResponse(['message' => 'ok']);

        $this->assertSame('{"message":"ok"}', $response->body());
        $this->assertSame('application/json', $response->headers()['Content-Type']);
        $this->assertSame(200, $response->status());
    }

    #[Test]
    public function aceita_status_customizado(): void
    {
        $response = new JsonResponse(['error' => 'Not Found'], 404);

        $this->assertSame(404, $response->status());
    }
}
