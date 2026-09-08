<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\User\ChangeOwnPassword;
use App\Application\User\DTO\UserProfile;
use App\Application\User\ListUsers;
use App\Application\User\PurgeAccount;
use App\Application\User\RegisterUser;
use App\Application\User\RequestPasswordReset;
use App\Application\User\ResetPassword;
use App\Application\User\RestoreAccount;
use App\Application\User\TrashAccount;
use App\Application\User\UpdateUserProfile;
use App\Application\User\UserFinder;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Domain\User\UserRole;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\RequestActor;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Pagination\PaginationPolicy;
use App\Infrastructure\Validation\Validator;

/**
 * Cobre tanto o self-service (`/me`) quanto o CRUD admin (`/users`) -- é o
 * mesmo recurso (`User`), a diferença entre as duas é autorização (`roles`
 * da rota), não domínio, então não justifica duas classes. `targetUserId()`
 * resolve o alvo pelo `{id}` da rota quando existe (admin) ou pelo próprio
 * JWT quando não (self) -- o resto do método não precisa saber qual dos
 * dois é.
 */
final readonly class UserController
{
    public function __construct(
        private ListUsers $listUsers,
        private UserFinder $finder,
        private RegisterUser $registerUser,
        private UpdateUserProfile $updateUserProfile,
        private RequestPasswordReset $requestPasswordReset,
        private ResetPassword $resetPassword,
        private ChangeOwnPassword $changeOwnPassword,
        private TrashAccount $trashAccount,
        private RestoreAccount $restoreAccount,
        private PurgeAccount $purgeAccount,
        private PaginationPolicy $pagination,
    ) {
    }

    /** Admin-only. */
    public function index(Request $request): Response
    {
        [$page, $perPage] = $this->pagination->resolve($request->query('page'), $request->query('per_page'));
        $result = ($this->listUsers)($perPage, ($page - 1) * $perPage);

        return Response::paginated(
            array_map(static fn (UserProfile $profile): array => $profile->toArray(), $result['items']),
            $page,
            $perPage,
            $result['total'],
        );
    }

    /** Cadastro público -- só seller/customer, nunca admin (isso só via store(), admin-only). */
    public function register(Request $request): Response
    {
        return $this->create($request, 'required|in:seller,customer');
    }

    /** Admin cria qualquer role, inclusive outro admin. */
    public function store(Request $request): Response
    {
        return $this->create($request, 'required|in:admin,seller,customer');
    }

    public function show(Request $request): Response
    {
        return Response::success(UserProfile::fromUser($this->finder->findOrFail($this->targetUserId($request)))->toArray());
    }

    public function update(Request $request): Response
    {
        $data = Validator::validate($request->json(), [
            'name' => 'max:255',
            'phone' => 'max:20',
            'role' => 'in:admin,seller,customer',
        ]);

        $user = ($this->updateUserProfile)(
            $this->targetUserId($request),
            $data,
            $request->param('id') !== null,
            RequestActor::fromRequest($request),
        );

        return Response::success(UserProfile::fromUser($user)->toArray());
    }

    /**
     * Sem `Bearer` nenhum, e-mail existindo ou não, sempre 200 -- não vaza se
     * a conta existe, mesma regra do login.
     */
    public function requestPasswordReset(Request $request): Response
    {
        $data = Validator::validate($request->json(), ['email' => 'required|email']);
        ($this->requestPasswordReset)($data->string('email'));

        return Response::success(['message' => 'If the email exists, a reset link was sent.']);
    }

    /**
     * O corpo decide, igual `POST /oauth/token`: `reset_token` presente ->
     * esqueci-minha-senha (sem Bearer, prova de identidade é o token do
     * e-mail); senão -> troca autenticada (`current_password`, token válido
     * prova quem é o usuário, não que ele ainda sabe a senha).
     */
    public function updatePassword(Request $request): Response
    {
        $body = $request->jsonFields();

        if (array_key_exists('reset_token', $body)) {
            $data = Validator::validate($body, [
                'reset_token' => 'required',
                'password' => 'required|min:8',
            ]);

            ($this->resetPassword)($data->string('reset_token'), $data->string('password'), RequestActor::fromRequest($request));
        } else {
            $data = Validator::validate($body, [
                'current_password' => 'required',
                'password' => 'required|min:8',
            ]);

            ($this->changeOwnPassword)(
                $this->targetUserId($request),
                $data->string('current_password'),
                $data->string('password'),
                RequestActor::fromRequest($request),
            );
        }

        return Response::success(['message' => 'Password updated.']);
    }

    public function destroy(Request $request): Response
    {
        ($this->trashAccount)($this->targetUserId($request), RequestActor::fromRequest($request));

        return Response::success(['message' => 'Account moved to trash. Log in again within 30 days to restore it, or it will be permanently anonymized.']);
    }

    /** Admin-only -- recupera uma conta na lixeira sem esperar o dono logar de novo. */
    public function restore(Request $request): Response
    {
        ($this->restoreAccount)($this->targetUserId($request), RequestActor::fromRequest($request));

        return Response::success(['message' => 'Account restored.']);
    }

    /** Apaga em definitivo agora, sem esperar os 30 dias -- self (`/me/purge`) ou admin (`/users/{id}/purge`). */
    public function purge(Request $request): Response
    {
        ($this->purgeAccount)($this->targetUserId($request), RequestActor::fromRequest($request));

        return Response::success(['message' => 'Account permanently deleted.']);
    }

    private function create(Request $request, string $roleRule): Response
    {
        $data = Validator::validate($request->json(), [
            'name' => 'required|max:255',
            'email' => 'required|email',
            'phone' => 'max:20',
            'password' => 'required|min:8',
            'role' => $roleRule,
        ]);

        $user = ($this->registerUser)(
            $data->string('name'),
            $data->string('email'),
            $data->stringOrNull('phone'),
            $data->string('password'),
            UserRole::from($data->string('role')),
            RequestActor::fromRequest($request),
        );

        return Response::success(UserProfile::fromUser($user)->toArray(), 201);
    }

    /**
     * `{id}` da rota (admin em /users/{id}) ou o próprio JWT (self em /me) --
     * quem chama não precisa saber qual dos dois é. `/me/password` é pública
     * (aceita o caminho de reset sem Bearer nenhum), então aqui não dá mais
     * pra assumir que sempre existe claims -- vira 401 limpo, não erro solto.
     */
    private function targetUserId(Request $request): string
    {
        $id = $request->param('id') ?? RequestActor::fromRequest($request)->actorId;

        if ($id === null) {
            throw new DomainException('Authentication required.', DomainErrorType::Unauthorized);
        }

        return $id;
    }
}
