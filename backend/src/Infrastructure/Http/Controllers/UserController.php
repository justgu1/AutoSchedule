<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\Ports\AuditLogger;
use App\Domain\Auth\PasswordResetToken;
use App\Domain\Auth\Ports\PasswordResetTokenRepository;
use App\Domain\Auth\Ports\RefreshTokenRepository;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Domain\Ports\Queue;
use App\Domain\Users\DTO\UserProfile;
use App\Domain\Users\Ports\UserRepository;
use App\Domain\Users\User;
use App\Domain\Users\UserRole;
use App\Domain\Users\UserStatus;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Mail\MailTemplate;
use App\Infrastructure\Mail\SendEmailJob;
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
final readonly class UserController
{
    public function __construct(
        private UserRepository $users,
        private RefreshTokenRepository $refreshTokens,
        private AuditLogger $audit,
        private PaginationPolicy $pagination,
        private PasswordResetTokenRepository $passwordResetTokens,
        private Queue $queue,
        private int $passwordResetTtl,
        private string $frontendUrl,
        private string $passwordResetTemplatePath,
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
     * Self troca name/phone e, no máximo, escala a própria role de `customer`
     * pra `seller` (`User::isEligibleForSelfServiceRoleChange`) -- qualquer
     * outra transição no caminho self é rejeitada. Admin mexendo em outro id
     * troca pra qualquer role (com a trava do último admin).
     */
    public function update(Request $request): Response
    {
        $user = $this->requireUser($request);
        $managingAnotherUser = $request->param('id') !== null;

        $rules = ['name' => 'max:255', 'phone' => 'max:20', 'role' => 'in:admin,seller,customer'];

        $data = Validator::validate($request->json(), $rules);
        $previousRole = $user->role;
        $user = $user->withProfile($data['name'] ?? $user->name, $data['phone'] ?? $user->phone);

        if ($managingAnotherUser && array_key_exists('role', $data)) {
            $newRole = UserRole::from($data['role']);

            if ($previousRole === UserRole::Admin && $newRole !== UserRole::Admin) {
                $this->assertNotLastAdmin();
            }

            $user = $user->withRole($newRole);
        } elseif (!$managingAnotherUser && array_key_exists('role', $data)) {
            $newRole = UserRole::from($data['role']);

            if (!$user->isEligibleForSelfServiceRoleChange($newRole)) {
                throw new DomainException('Only customer accounts can self-upgrade to seller.', DomainErrorType::Forbidden);
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

    /**
     * Sem `Bearer` nenhum, e-mail existindo ou não, sempre 200 -- não vaza se
     * a conta existe, mesma regra do login.
     */
    public function requestPasswordReset(Request $request): Response
    {
        $data = Validator::validate($request->json(), ['email' => 'required|email']);
        $user = $this->users->findByEmail($data['email']);

        if ($user instanceof \App\Domain\Users\User) {
            [$rawToken, $token] = PasswordResetToken::issue($user->id, $this->passwordResetTtl);
            $this->passwordResetTokens->insert($token);

            $html = MailTemplate::render($this->passwordResetTemplatePath, [
                'RESET_LINK' => sprintf('%s/reset-password?token=%s', $this->frontendUrl, $rawToken),
                'EXPIRES_MINUTES' => (string) intdiv($this->passwordResetTtl, 60),
            ]);

            $this->queue->push(SendEmailJob::class, [
                'to' => $user->email,
                'subject' => 'Redefinir senha -- AutoSchedule',
                'html_body' => $html,
            ]);
        }

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
        $body = $request->json();

        if (array_key_exists('reset_token', $body)) {
            return $this->resetPassword($request, $body);
        }

        return $this->changeOwnPassword($request, $body);
    }

    /** @param array<string, mixed> $body */
    private function resetPassword(Request $request, array $body): Response
    {
        $data = Validator::validate($body, [
            'reset_token' => 'required',
            'password' => 'required|min:8',
        ]);

        $token = $this->passwordResetTokens->findByRawToken($data['reset_token']);
        $user = $token instanceof \App\Domain\Auth\PasswordResetToken ? $this->users->findById($token->userId) : null;

        if (!$token instanceof \App\Domain\Auth\PasswordResetToken || $token->isUsed() || $token->isExpired() || !$user instanceof \App\Domain\Users\User) {
            throw new DomainException('Invalid or expired reset token.', DomainErrorType::Unauthorized);
        }

        $this->applyNewPassword($user, $data['password'], 'reset', $request);
        $this->passwordResetTokens->markUsed($token->id);
        // Um reset bem-sucedido invalida qualquer outro link ainda pendente do
        // mesmo usuário -- não deixa um link antigo ainda funcionando depois.
        $this->passwordResetTokens->invalidateAllForUser($user->id);
        $this->refreshTokens->revokeAllForUser($user->id);

        return Response::success(['message' => 'Password updated.']);
    }

    /** @param array<string, mixed> $body */
    private function changeOwnPassword(Request $request, array $body): Response
    {
        $user = $this->requireUser($request);
        $data = Validator::validate($body, [
            'current_password' => 'required',
            'password' => 'required|min:8',
        ]);

        if (!$user->verifyPassword($data['current_password'])) {
            throw new DomainException('Current password is incorrect.', DomainErrorType::Unauthorized);
        }

        $this->applyNewPassword($user, $data['password'], 'self', $request);

        return Response::success(['message' => 'Password updated.']);
    }

    private function applyNewPassword(User $user, string $password, string $via, Request $request): void
    {
        $this->users->update($user->withNewPassword($password));
        $this->audit->record(AuditEvent::PasswordChanged, $user->id, $user->id, ['via' => $via], $request->ip(), $request->header('user-agent'));
    }

    /**
     * Move pra lixeira (reversível por 30 dias -- login de novo restaura, ou
     * `restore()`/`purge()` abaixo) e revoga todo refresh token, ninguém
     * continua logado depois disso. Roda dentro da transação já aberta pelo
     * AuthContextMiddleware.
     */
    public function destroy(Request $request): Response
    {
        $user = $this->requireUser($request);

        if ($user->role === UserRole::Admin) {
            $this->assertNotLastAdmin();
        }

        $this->users->trash($user->id);
        $this->refreshTokens->revokeAllForUser($user->id);
        $this->audit->record(AuditEvent::AccountTrashed, $request->attribute('auth')->subject, $user->id, [], $request->ip(), $request->header('user-agent'));

        return Response::success(['message' => 'Account moved to trash. Log in again within 30 days to restore it, or it will be permanently anonymized.']);
    }

    /** Admin-only -- recupera uma conta na lixeira sem esperar o dono logar de novo. */
    public function restore(Request $request): Response
    {
        $user = $this->requireUser($request);

        if (!$user->isEligibleForRestore()) {
            throw new DomainException('This account is not in the trash (or was already permanently deleted).', DomainErrorType::Conflict);
        }

        $this->users->restore($user->id);
        $this->audit->record(AuditEvent::AccountRestored, $request->attribute('auth')->subject, $user->id, [], $request->ip(), $request->header('user-agent'));

        return Response::success(['message' => 'Account restored.']);
    }

    /** Apaga em definitivo agora, sem esperar os 30 dias -- self (`/me/purge`) ou admin (`/users/{id}/purge`). */
    public function purge(Request $request): Response
    {
        $user = $this->requireUser($request);

        if ($user->status !== UserStatus::Trashed) {
            throw new DomainException('This account is not in the trash.', DomainErrorType::Conflict);
        }

        $this->users->anonymizeAndSoftDelete($user->id);
        $this->audit->record(AuditEvent::AccountPurged, $request->attribute('auth')->subject, $user->id, [], $request->ip(), $request->header('user-agent'));

        return Response::success(['message' => 'Account permanently deleted.']);
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

    /**
     * `{id}` da rota (admin em /users/{id}) ou o próprio JWT (self em /me) --
     * quem chama não precisa saber qual dos dois é. `/me/password` é pública
     * (aceita o caminho de reset sem Bearer nenhum), então aqui não dá mais
     * pra assumir que sempre existe claims -- vira 401 limpo, não erro solto.
     */
    private function requireUser(Request $request): User
    {
        $id = $request->param('id');

        if ($id === null) {
            $claims = $request->attribute('auth');

            if ($claims === null) {
                throw new DomainException('Authentication required.', DomainErrorType::Unauthorized);
            }

            $id = $claims->subject;
        }

        $user = $this->users->findById($id);

        if (!$user instanceof \App\Domain\Users\User) {
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
