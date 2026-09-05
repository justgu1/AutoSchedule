<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Domain\Auth\DTO\TokenPair;
use App\Domain\Auth\OAuthService;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Validation\Validator;

final class OAuthController
{
    public function __construct(private readonly OAuthService $oauth)
    {
    }

    /**
     * Sem `grant_type` de propósito -- quem integra não devia aprender
     * vocabulário de OAuth só pra logar. `refresh_token` presente manda mais
     * que qualquer outro campo: se sobrou email/senha no body por engano ou
     * reaproveitado de outro request, ignora, renova mesmo assim.
     */
    public function token(Request $request): Response
    {
        $body = $request->json();

        if (array_key_exists('refresh_token', $body)) {
            return $this->refresh($body);
        }

        if (array_key_exists('email', $body) || array_key_exists('password', $body)) {
            return $this->login($body);
        }

        throw new DomainException(
            'Invalid data.',
            DomainErrorType::Validation,
            ['body' => 'Send {email, password} to log in or {refresh_token} to renew.'],
        );
    }

    /** @param array<string, mixed> $body */
    private function login(array $body): Response
    {
        $data = Validator::validate($body, [
            'client_id' => 'required',
            'email' => 'required|email',
            'password' => 'required',
        ]);

        return $this->tokenResponse($this->oauth->loginWithPassword($data['client_id'], $data['email'], $data['password']));
    }

    /** @param array<string, mixed> $body */
    private function refresh(array $body): Response
    {
        $data = Validator::validate($body, [
            'client_id' => 'required',
            'refresh_token' => 'required',
        ]);

        return $this->tokenResponse($this->oauth->refresh($data['client_id'], $data['refresh_token']));
    }

    private function tokenResponse(TokenPair $tokenPair): Response
    {
        return Response::success([
            'access_token' => $tokenPair->accessToken,
            'token_type' => 'Bearer',
            'expires_in' => $tokenPair->expiresIn,
            'refresh_token' => $tokenPair->refreshToken,
            'scope' => implode(' ', $tokenPair->scopes),
        ]);
    }
}
