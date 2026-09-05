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
    public function __construct(
        private readonly OAuthService $oauth,
        private readonly int $refreshTokenTtl,
        private readonly bool $cookieSecure,
    ) {
    }

    /**
     * Sem `grant_type` de propósito -- quem integra não devia aprender
     * vocabulário de OAuth só pra logar. Ordem importa quando o body traz
     * campo de mais de um formato por engano: `refresh_token` manda mais que
     * `email`/`password`, que manda mais que `client_secret` (M2M).
     */
    public function token(Request $request): Response
    {
        $body = $request->json();

        if (array_key_exists('refresh_token', $body)) {
            return $this->refresh($request, $body);
        }

        if (array_key_exists('email', $body) || array_key_exists('password', $body)) {
            return $this->login($request, $body);
        }

        if (array_key_exists('client_secret', $body)) {
            return $this->clientCredentials($request, $body);
        }

        throw new DomainException(
            'Invalid data.',
            DomainErrorType::Validation,
            ['body' => 'Send {email, password} to log in, {refresh_token} to renew, or {client_id, client_secret} for machine-to-machine access.'],
        );
    }

    /** @param array<string, mixed> $body */
    private function login(Request $request, array $body): Response
    {
        $data = Validator::validate($body, [
            'client_id' => 'required',
            'email' => 'required|email',
            'password' => 'required',
        ]);

        return $this->tokenResponse($this->oauth->loginWithPassword(
            $data['client_id'],
            $data['email'],
            $data['password'],
            $request->ip(),
            $request->header('user-agent'),
        ));
    }

    /** @param array<string, mixed> $body */
    private function refresh(Request $request, array $body): Response
    {
        $data = Validator::validate($body, [
            'client_id' => 'required',
            'refresh_token' => 'required',
        ]);

        return $this->tokenResponse($this->oauth->refresh(
            $data['client_id'],
            $data['refresh_token'],
            $request->ip(),
            $request->header('user-agent'),
        ));
    }

    /** @param array<string, mixed> $body */
    private function clientCredentials(Request $request, array $body): Response
    {
        $data = Validator::validate($body, [
            'client_id' => 'required',
            'client_secret' => 'required',
        ]);

        $tokenPair = $this->oauth->clientCredentials(
            $data['client_id'],
            $data['client_secret'],
            $request->ip(),
            $request->header('user-agent'),
        );

        // M2M: sem browser no meio, sem sessão pra manter em cookie -- só o JSON de sempre.
        return Response::success([
            'access_token' => $tokenPair->accessToken,
            'token_type' => 'Bearer',
            'expires_in' => $tokenPair->expiresIn,
            'scope' => implode(' ', $tokenPair->scopes),
        ]);
    }

    /** Lê o refresh token do cookie (SPA não manda no corpo) -- sem cookie, não tem o quê revogar, mas ainda limpa os cookies do client. */
    public function logout(Request $request): Response
    {
        $rawRefreshToken = $request->cookie('refresh_token');

        if ($rawRefreshToken !== null) {
            $this->oauth->logout($rawRefreshToken);
        }

        return Response::success(['message' => 'Logged out.'])
            ->withCookie('access_token', '', maxAge: -1, secure: $this->cookieSecure)
            ->withCookie('refresh_token', '', maxAge: -1, secure: $this->cookieSecure);
    }

    /**
     * O corpo JSON continua com os tokens (curl/Postman/scripts não mudam
     * nada) -- os cookies HttpOnly são só pra quem tem browser no meio (a
     * SPA nunca lê o token do corpo, confia só no cookie).
     */
    private function tokenResponse(TokenPair $tokenPair): Response
    {
        $response = Response::success([
            'access_token' => $tokenPair->accessToken,
            'token_type' => 'Bearer',
            'expires_in' => $tokenPair->expiresIn,
            'refresh_token' => $tokenPair->refreshToken,
            'scope' => implode(' ', $tokenPair->scopes),
        ]);

        $response = $response->withCookie(
            'access_token',
            $tokenPair->accessToken,
            maxAge: $tokenPair->expiresIn,
            secure: $this->cookieSecure,
        );

        if ($tokenPair->refreshToken !== null) {
            $response = $response->withCookie(
                'refresh_token',
                $tokenPair->refreshToken,
                maxAge: $this->refreshTokenTtl,
                secure: $this->cookieSecure,
            );
        }

        return $response;
    }
}
