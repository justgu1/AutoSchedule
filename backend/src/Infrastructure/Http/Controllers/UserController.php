<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\Ports\AuditLogger;
use App\Domain\Auth\Ports\RefreshTokenRepository;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Domain\Users\DTO\UserProfile;
use App\Domain\Users\Ports\UserRepository;
use App\Domain\Users\User;
use App\Domain\Users\UserRole;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Pagination\PaginationPolicy;
use App\Infrastructure\Validation\Validator;

/**
 * Cobre tanto o self-service (`/me`) quanto o CRUD admin (`/users`) -- é o
 * mesmo recurso (`User`), a diferença entre as duas é autorização (`roles`
 * da rota), não domínio, então não justifica duas classes. `requireUser()`
 * resolve o alvo pelo `{id}` da rota quando existe (admin) ou pelo próprio
 * JWT quando não (self) -- o resto do método não precisa saber qual dos
 * dois é.
 */
final class UserController
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly RefreshTokenRepository $refreshTokens,
        private readonly AuditLogger $audit,
        private readonly PaginationPolicy $pagination,
    ) {
    }

    /** Admin-only. */
    public function index(Request $request): Response
    {
        [$page, $perPage] = $this->pagination->resolve($request->query('page'), $request->query('per_page'));

        $profiles = array_map(
            static fn (User $user): array => UserProfile::fromUser($user)->toArray(),
            $this->users->findPage($perPage, ($page - 1) * $perPage),
        );

        return Response::paginated($profiles, $page, $perPage, $this->users->count());
    }

    /** Cadastro público -- só seller/customer, nunca admin (isso só via store(), admin-only). Sem autenticação, o alvo criado é o próprio actor. */
    public function register(Request $request): Response
    {
        $data = Validator::validate($request->json(), [
            'name' => 'required|max:255',
            'email' => 'required|email',
            'phone' => 'max:20',
            'password' => 'required|min:8',
            'role' => 'required|in:seller,customer',
        ]);

        $user = $this->createUser($data, UserRole::from($data['role']));
        $this->audit->record(AuditEvent::UserCreated, $user->id, $user->id, ['role' => $user->role->value], $request->ip(), $request->header('user-agent'));

        return Response::success(UserProfile::fromUser($user)->toArray(), 201);
    }

    /** Admin cria qualquer role, inclusive outro admin. */
    public function store(Request $request): Response
    {
        $data = Validator::validate($request->json(), [
            'name' => 'required|max:255',
            'email' => 'required|email',
            'phone' => 'max:20',
            'password' => 'required|min:8',
            'role' => 'required|in:admin,seller,customer',
        ]);

        $user = $this->createUser($data, UserRole::from($data['role']));
        $this->audit->record(AuditEvent::UserCreated, $request->attribute('auth')->subject, $user->id, ['role' => $user->role->value], $request->ip(), $request->header('user-agent'));

        return Response::success(UserProfile::fromUser($user)->toArray(), 201);
    }

    public function show(Request $request): Response
    {
        return Response::success(UserProfile::fromUser($this->requireUser($request))->toArray());
    }

    /**
     * Self só troca name/phone; admin mexendo em outro id também troca role
     * (com a trava do último admin). `roles` só entra na validação quando a
     * rota tem `{id}` (admin) -- self nunca consegue mandar esse campo.
     */
    public function update(Request $request): Response
    {
        $user = $this->requireUser($request);
        $managingAnotherUser = $request->param('id') !== null;

        $rules = ['name' => 'max:255', 'phone' => 'max:20'];

        if ($managingAnotherUser) {
            $rules['role'] = 'in:admin,seller,customer';
        }

        $data = Validator::validate($request->json(), $rules);
        $previousRole = $user->role;
        $user = $user->withProfile($data['name'] ?? $user->name, $data['phone'] ?? $user->phone);

        if ($managingAnotherUser && array_key_exists('role', $data)) {
            $newRole = UserRole::from($data['role']);

            if ($previousRole === UserRole::Admin && $newRole !== UserRole::Admin) {
                $this->assertNotLastAdmin();
            }

            $user = $user->withRole($newRole);
        }

        $this->users->update($user);
        // Só o nome dos campos de perfil alterados (sem valor -- não duplica PII),
        // mas role muda quem pode fazer o quê no sistema, então guarda de/para inteiro.
        $context = ['fields' => array_keys($data)];

        if ($user->role !== $previousRole) {
            $context['role'] = ['from' => $previousRole->value, 'to' => $user->role->value];
        }

        $this->audit->record(AuditEvent::ProfileUpdated, $request->attribute('auth')->subject, $user->id, $context, $request->ip(), $request->header('user-agent'));

        return Response::success(UserProfile::fromUser($user)->toArray());
    }

    /** Só o próprio usuário -- não existe em /users/{id}, admin não reseta senha de ninguém. Exige a senha atual: token válido prova quem é o usuário, não que ele ainda sabe a senha. */
    public function updatePassword(Request $request): Response
    {
        $user = $this->requireUser($request);
        $data = Validator::validate($request->json(), [
            'current_password' => 'required',
            'password' => 'required|min:8',
        ]);

        if (!$user->verifyPassword($data['current_password'])) {
            throw new DomainException('Current password is incorrect.', DomainErrorType::Unauthorized);
        }

        $this->users->update($user->withNewPassword($data['password']));
        $this->audit->record(AuditEvent::PasswordChanged, $user->id, $user->id, [], $request->ip(), $request->header('user-agent'));

        return Response::success(['message' => 'Password updated.']);
    }

    /**
     * LGPD, direito ao esquecimento: anonimiza+soft-delete (nunca hard-delete)
     * e revoga todo refresh token do usuário -- ninguém continua logado depois
     * disso. Roda dentro da transação já aberta pelo AuthContextMiddleware.
     */
    public function destroy(Request $request): Response
    {
        $user = $this->requireUser($request);

        if ($user->role === UserRole::Admin) {
            $this->assertNotLastAdmin();
        }

        $this->users->anonymizeAndSoftDelete($user->id);
        $this->refreshTokens->revokeAllForUser($user->id);
        $this->audit->record(AuditEvent::AccountDeleted, $request->attribute('auth')->subject, $user->id, [], $request->ip(), $request->header('user-agent'));

        return Response::success(['message' => 'Account deleted.']);
    }

    /** @param array<string, mixed> $data */
    private function createUser(array $data, UserRole $role): User
    {
        if ($this->users->existsByEmail($data['email'])) {
            throw new DomainException('Email already in use.', DomainErrorType::Conflict, ['email' => 'Email already in use.']);
        }

        $user = User::register($data['name'], $data['email'], $data['phone'] ?? null, $data['password'], $role);
        $this->users->insert($user);

        return $user;
    }

    /** `{id}` da rota (admin em /users/{id}) ou o próprio JWT (self em /me) -- quem chama não precisa saber qual dos dois é. */
    private function requireUser(Request $request): User
    {
        $id = $request->param('id') ?? $request->attribute('auth')->subject;
        $user = $this->users->findById($id);

        if ($user === null) {
            throw new DomainException('User not found.', DomainErrorType::NotFound);
        }

        return $user;
    }

    /** Chamado só quando o usuário já é admin -- barra o passo que o tiraria do papel (delete ou troca de role) se ele for o único. */
    private function assertNotLastAdmin(): void
    {
        if ($this->users->countByRole(UserRole::Admin) <= 1) {
            throw new DomainException('Cannot remove the last remaining admin.', DomainErrorType::Conflict);
        }
    }
}
