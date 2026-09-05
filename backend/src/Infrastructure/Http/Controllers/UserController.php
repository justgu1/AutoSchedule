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
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Validation\Validator;

final class UserController
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly RefreshTokenRepository $refreshTokens,
        private readonly AuditLogger $audit,
    ) {
    }

    public function show(Request $request): Response
    {
        return Response::success(UserProfile::fromUser($this->currentUser($request))->toArray());
    }

    /** Só perfil (name/phone) -- troca de senha é uma ação de segurança à parte, ver updatePassword(). */
    public function update(Request $request): Response
    {
        $user = $this->currentUser($request);
        $data = Validator::validate($request->json(), [
            'name' => 'max:255',
            'phone' => 'max:20',
        ]);

        $user = $user->withProfile($data['name'] ?? $user->name, $data['phone'] ?? $user->phone);
        $this->users->update($user);
        // Só o nome dos campos alterados, nunca o valor -- audit_logs não é
        // outro lugar pra duplicar PII que já mora em users.
        $this->audit->record(AuditEvent::ProfileUpdated, $user->id, $user->id, ['fields' => array_keys($data)], $request->ip(), $request->header('user-agent'));

        return Response::success(UserProfile::fromUser($user)->toArray());
    }

    /** Exige a senha atual -- token válido prova quem é o usuário, não que ele ainda sabe a senha. */
    public function updatePassword(Request $request): Response
    {
        $user = $this->currentUser($request);
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
        $userId = $request->attribute('auth')->subject;

        $this->users->anonymizeAndSoftDelete($userId);
        $this->refreshTokens->revokeAllForUser($userId);
        $this->audit->record(AuditEvent::AccountDeleted, $userId, $userId, [], $request->ip(), $request->header('user-agent'));

        return Response::success(['message' => 'Account deleted.']);
    }

    private function currentUser(Request $request): User
    {
        $user = $this->users->findById($request->attribute('auth')->subject);

        if ($user === null) {
            throw new DomainException('User not found.', DomainErrorType::NotFound);
        }

        return $user;
    }
}
