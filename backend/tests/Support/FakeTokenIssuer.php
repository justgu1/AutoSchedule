<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Auth\Ports\TokenIssuer;
use App\Domain\Auth\ValueObjects\AccessTokenClaims;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;

final readonly class FakeTokenIssuer implements TokenIssuer
{
    /** @param array<string, AccessTokenClaims> $tokens */
    public function __construct(private array $tokens = [])
    {
    }

    public function issueAccessToken(AccessTokenClaims $claims): string
    {
        throw new \LogicException('Not used in tests.');
    }

    public function decodeAccessToken(string $token): AccessTokenClaims
    {
        return $this->tokens[$token] ?? throw new DomainException('Invalid or expired access token.', DomainErrorType::Unauthorized);
    }
}
