<?php

declare(strict_types=1);

namespace App\Application\User;

use App\Application\Shared\ActorContext;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\Ports\AuditLogger;
use App\Domain\Auth\PasswordResetToken;
use App\Domain\Auth\Ports\PasswordResetTokenRepository;
use App\Domain\Auth\Ports\RefreshTokenRepository;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Domain\User\Ports\UserRepository;
use App\Domain\User\User;

/** Caminho "esqueci minha senha": a prova de identidade é o token do e-mail, não um Bearer. */
final readonly class ResetPassword
{
    public function __construct(
        private PasswordResetTokenRepository $passwordResetTokens,
        private UserRepository $users,
        private RefreshTokenRepository $refreshTokens,
        private AuditLogger $audit,
    ) {
    }

    public function __invoke(string $rawToken, string $newPassword, ActorContext $context): void
    {
        $token = $this->passwordResetTokens->findByRawToken($rawToken);
        $user = $token instanceof PasswordResetToken ? $this->users->findById($token->userId) : null;

        if (!$token instanceof PasswordResetToken || $token->isUsed() || $token->isExpired() || !$user instanceof User) {
            throw new DomainException('Invalid or expired reset token.', DomainErrorType::Unauthorized);
        }

        $this->users->update($user->withNewPassword($newPassword));
        $this->audit->record(AuditEvent::PasswordChanged, $user->id, 'User', $user->id, ['via' => 'reset'], $context->ipAddress, $context->userAgent);

        $this->passwordResetTokens->markUsed($token->id);
        // Um reset bem-sucedido invalida qualquer outro link ainda pendente do
        // mesmo usuário -- não deixa um link antigo ainda funcionando depois.
        $this->passwordResetTokens->invalidateAllForUser($user->id);
        $this->refreshTokens->revokeAllForUser($user->id);
    }
}
