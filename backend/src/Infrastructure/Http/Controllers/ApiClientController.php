<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Auth\CreateApiClient;
use App\Application\Auth\ListApiClients;
use App\Application\Auth\RevokeApiClient;
use App\Application\Auth\RotateApiClientSecret;
use App\Domain\Auth\OAuthClient;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\RequestActor;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Validation\Validator;

/** Credenciais `client_credentials` self-service, sempre escopadas a "eu mesmo" -- sem variante administrada por outro usuário. */
final readonly class ApiClientController
{
    public function __construct(
        private ListApiClients $listApiClients,
        private CreateApiClient $createApiClient,
        private RotateApiClientSecret $rotateApiClientSecret,
        private RevokeApiClient $revokeApiClient,
    ) {
    }

    public function index(Request $request): Response
    {
        $clients = ($this->listApiClients)(RequestActor::fromRequest($request));

        return Response::success(array_map($this->summary(...), $clients));
    }

    public function store(Request $request): Response
    {
        $data = Validator::validate($request->json(), ['name' => 'required|max:120']);
        [$client, $secret] = ($this->createApiClient)($data->string('name'), RequestActor::fromRequest($request));

        return Response::success($this->withSecret($client, $secret), 201);
    }

    public function rotateSecret(Request $request): Response
    {
        [$client, $secret] = ($this->rotateApiClientSecret)($request->param('id'), RequestActor::fromRequest($request));

        return Response::success($this->withSecret($client, $secret));
    }

    public function destroy(Request $request): Response
    {
        ($this->revokeApiClient)($request->param('id'), RequestActor::fromRequest($request));

        return Response::success(['message' => 'API client revoked.']);
    }

    /** @return array<string, mixed> */
    private function summary(OAuthClient $client): array
    {
        return [
            'id' => $client->id,
            'client_id' => $client->clientId,
            'name' => $client->name,
            'created_at' => $client->createdAt->format(DATE_ATOM),
            'revoked_at' => $client->revokedAt?->format(DATE_ATOM),
        ];
    }

    /**
     * `client_secret` só aparece nesta resposta -- em texto puro, e nunca mais recuperável depois dela.
     *
     * @return array<string, mixed>
     */
    private function withSecret(OAuthClient $client, string $secret): array
    {
        return $this->summary($client) + ['client_secret' => $secret];
    }
}
