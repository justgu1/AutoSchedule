<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

enum HttpMethod: string
{
    case Get = 'GET';
    case Post = 'POST';
    case Put = 'PUT';
    case Patch = 'PATCH';
    case Delete = 'DELETE';

    public function hasBody(): bool
    {
        return $this !== self::Get;
    }
}
