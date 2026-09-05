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

/** CRUD admin de /users -- distinto do self-service (UserController, /me): aqui um admin gerencia a conta de outra pessoa. */
final class UsersController
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly RefreshTokenRepository $refreshTokens,
        private readonly AuditLogger $audit,
        private readonly PaginationPolicy $pagination,
    ) {
    }

    public function index(Request $request): Response
    {
        [$page, $perPage] = $this->pagination->resolve($request->query('page'), $request->query('per_page'));

        $profiles = array_map(
            static fn (User $user): array => UserProfile::fromUser($user)->toArray(),
            $this->users->findPage($perPage, ($page - 1) * $perPage),
        );

        return Response::paginated($profiles, $page, $perPage, $this->users->count());
    }

    public function show(Request $request): Response
    {
        return Response::success(UserProfile::fromUser($this->requireUser($request->param('id')))->toArray());
    }

    public function store(Request $request): Response
    {
        $data = Validator::validate($request->json(), [
            'name' => 'required|max:255',
            'email' => 'required|email',
            'phone' => 'max:20',
            'password' => 'required|min:8',
            'role' => 'required|in:admin,seller,customer',
        ]);

        if ($this->users->existsByEmail($data['email'])) {
            throw new DomainException('Email already in use.', DomainErrorType::Conflict, ['email' => 'Email already in use.']);
        }

        $user = User::register($data['name'], $data['email'], $data['phone'] ?? null, $data['password'], UserRole::from($data['role']));
        $this->users->insert($user);
        $this->audit->record(
            AuditEvent::UserCreated,
            $request->attribute('auth')->subject,
            $user->id,
            ['role' => $user->role->value],
            $request->ip(),
            $request->header('user-agent'),
        );

        return Response::success(UserProfile::fromUser($user)->toArray(), 201);
    }

    public function update(Request $request): Response
    {
        $user = $this->requireUser($request->param('id'));
        $data = Validator::validate($request->json(), [
            'name' => 'max:255',
            'phone' => 'max:20',
            'role' => 'in:admin,seller,customer',
        ]);
        $previousRole = $user->role;

        $user = $user->withProfile($data['name'] ?? $user->name, $data['phone'] ?? $user->phone);

        if (array_key_exists('role', $data)) {
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

        $this->audit->record(
            AuditEvent::ProfileUpdated,
            $request->attribute('auth')->subject,
            $user->id,
            $context,
            $request->ip(),
            $request->header('user-agent'),
        );

        return Response::success(UserProfile::fromUser($user)->toArray());
    }

    public function destroy(Request $request): Response
    {
        $user = $this->requireUser($request->param('id'));

        if ($user->role === UserRole::Admin) {
            $this->assertNotLastAdmin();
        }

        $this->users->anonymizeAndSoftDelete($user->id);
        $this->refreshTokens->revokeAllForUser($user->id);
        $this->audit->record(
            AuditEvent::AccountDeleted,
            $request->attribute('auth')->subject,
            $user->id,
            [],
            $request->ip(),
            $request->header('user-agent'),
        );

        return Response::success(['message' => 'User deleted.']);
    }

    private function requireUser(?string $id): User
    {
        $user = $id !== null ? $this->users->findById($id) : null;

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
