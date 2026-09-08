<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Auth\DTO\TokenPair;
use App\Application\Auth\IssueServiceToken;
use App\Application\Auth\LoginWithGoogle;
use App\Application\Auth\LoginWithPassword;
use App\Application\Auth\Logout;
use App\Application\Auth\RefreshAccessToken;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\RequestActor;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Validation\Validator;

final readonly class OAuthController
{
    public function __construct(
        private LoginWithPassword $loginWithPassword,
        private RefreshAccessToken $refreshAccessToken,
        private LoginWithGoogle $loginWithGoogle,
        private IssueServiceToken $issueServiceToken,
        private Logout $revokeSession,
        private int $refreshTokenTtl,
        private bool $cookieSecure,
    ) {
    }

    /**
     * Sem `grant_type` de propósito -- quem integra não devia aprender
     * vocabulário de OAuth só pra logar. Ordem importa quando o body traz
     * campo de mais de um formato por engano: `refresh_token` manda mais que
     * `email`/`password`, que manda mais que `id_token` (Google), que manda
     * mais que `client_secret` (M2M).
     */
    public function token(Request $request): Response
    {
        $body = $request->jsonFields();

        if (array_key_exists('refresh_token', $body)) {
            return $this->refresh($request, $body);
        }

        if (array_key_exists('email', $body) || array_key_exists('password', $body)) {
            return $this->login($request, $body);
        }

        if (array_key_exists('id_token', $body)) {
            return $this->google($request, $body);
        }

        if (array_key_exists('client_secret', $body)) {
            return $this->clientCredentials($request, $body);
        }

        throw new DomainException(
            'Invalid data.',
            DomainErrorType::Validation,
            ['body' => 'Send {email, password} to log in, {refresh_token} to renew, {id_token} for Google login, or {client_id, client_secret} for machine-to-machine access.'],
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

        return $this->tokenResponse(($this->loginWithPassword)(
            $data->string('client_id'),
            $data->string('email'),
            $data->string('password'),
            RequestActor::fromRequest($request),
        ));
    }

    /** @param array<string, mixed> $body */
    private function refresh(Request $request, array $body): Response
    {
        $data = Validator::validate($body, [
            'client_id' => 'required',
            'refresh_token' => 'required',
        ]);

        return $this->tokenResponse(($this->refreshAccessToken)(
            $data->string('client_id'),
            $data->string('refresh_token'),
            RequestActor::fromRequest($request),
        ));
    }

    /** @param array<string, mixed> $body */
    private function google(Request $request, array $body): Response
    {
        $data = Validator::validate($body, [
            'client_id' => 'required',
            'id_token' => 'required',
        ]);

        return $this->tokenResponse(($this->loginWithGoogle)(
            $data->string('client_id'),
            $data->string('id_token'),
            RequestActor::fromRequest($request),
        ));
    }

    /** @param array<string, mixed> $body */
    private function clientCredentials(Request $request, array $body): Response
    {
        $data = Validator::validate($body, [
            'client_id' => 'required',
            'client_secret' => 'required',
        ]);

        $tokenPair = ($this->issueServiceToken)(
            $data->string('client_id'),
            $data->string('client_secret'),
            RequestActor::fromRequest($request),
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
            ($this->revokeSession)($rawRefreshToken);
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
            'account_restored' => $tokenPair->accountRestored,
        ]);

        $response = $response->withCookie(
            'access_token',
            $tokenPair->accessToken,
            maxAge: $tokenPair->expiresIn,
            secure: $this->cookieSecure,
        );

        if ($tokenPair->refreshToken !== null) {
            return $response->withCookie(
                'refresh_token',
                $tokenPair->refreshToken,
                maxAge: $this->refreshTokenTtl,
                secure: $this->cookieSecure,
            );
        }

        return $response;
    }
}
