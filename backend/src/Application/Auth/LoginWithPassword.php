<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Application\Auth\DTO\TokenPair;
use App\Application\Shared\ActorContext;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\Ports\AuditLogger;
use App\Domain\Auth\GrantType;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Domain\Shared\Email;
use App\Domain\User\Ports\UserRepository;
use App\Domain\User\User;

final readonly class LoginWithPassword
{
    public function __construct(
        private ClientAuthenticator $clients,
        private UserRepository $users,
        private TokenPairIssuer $tokenPairs,
        private AccountRestorer $accountRestorer,
        private AuditLogger $audit,
    ) {
    }

    public function __invoke(string $clientId, string $email, string $password, ActorContext $context): TokenPair
    {
        $client = $this->clients->authenticate($clientId, GrantType::Password);
        $user = $this->users->findByEmail(new Email($email));

        if (!$user instanceof User || !$user->verifyPassword($password)) {
            // Sem actor: identidade não foi provada. O alvo só é conhecido quando o e-mail existe.
            $this->audit->record(AuditEvent::LoginFailed, null, 'User', $user?->id, ['email' => $email], $context->ipAddress, $context->userAgent);

            // Mesma resposta pra e-mail inexistente e senha errada: não pode vazar se a conta existe.
            throw new DomainException('Invalid credentials.', DomainErrorType::Unauthorized);
        }

        $restored = $this->accountRestorer->restoreIfTrashed($user, $context);

        $tokenPair = $this->tokenPairs->issue($client, $user->id, $user->role, $client->allowedScopes, $restored);
        $this->audit->record(AuditEvent::LoginSucceeded, $user->id, 'User', $user->id, [], $context->ipAddress, $context->userAgent);

        return $tokenPair;
    }
}
