<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Application\Shared\ActorContext;
use App\Domain\Auth\ValueObjects\AccessTokenClaims;

/** Traduz o que o middleware de autenticação deixou no request pro contexto que os casos de uso entendem. */
final class RequestActor
{
    public static function fromRequest(Request $request): ActorContext
    {
        $claims = $request->attribute('auth');

        return new ActorContext(
            actorId: $claims instanceof AccessTokenClaims ? $claims->subject : null,
            role: $claims instanceof AccessTokenClaims ? $claims->role : null,
            ipAddress: $request->ip(),
            userAgent: $request->header('user-agent'),
        );
    }
}
