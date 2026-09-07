<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Application\Auth\DTO\TokenPair;
use App\Application\Shared\ActorContext;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\Ports\AuditLogger;
use App\Domain\Auth\GrantType;
use App\Domain\Auth\Ports\GoogleIdTokenVerifier;
use App\Domain\Auth\Ports\UserIdentityRepository;
use App\Domain\Auth\UserIdentity;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Domain\Shared\Email;
use App\Domain\User\Ports\UserRepository;
use App\Domain\User\User;
use App\Domain\User\UserRole;

/** E-mail verificado pelo Google já prova posse, então linka a conta existente sem mudar role. */
final readonly class LoginWithGoogle
{
    public function __construct(
        private ClientAuthenticator $clients,
        private GoogleIdTokenVerifier $googleVerifier,
        private UserIdentityRepository $identities,
        private UserRepository $users,
        private TokenPairIssuer $tokenPairs,
        private AccountRestorer $accountRestorer,
        private AuditLogger $audit,
    ) {
    }

    public function __invoke(string $clientId, string $idToken, ActorContext $context): TokenPair
    {
        $client = $this->clients->authenticate($clientId, GrantType::Google);
        $claims = $this->googleVerifier->verify($idToken);

        if (!$claims->emailVerified) {
            throw new DomainException('Google account email is not verified.', DomainErrorType::Unauthorized);
        }

        $email = new Email($claims->email);
        $identity = $this->identities->findByProvider('google', $claims->subject);

        if ($identity instanceof UserIdentity) {
            $user = $this->users->findById($identity->userId);

            if (!$user instanceof User) {
                throw new DomainException('Invalid Google credential.', DomainErrorType::Unauthorized);
            }

            $restored = $this->accountRestorer->restoreIfTrashed($user, $context);
            $this->audit->record(AuditEvent::LoginSucceeded, $user->id, 'User', $user->id, ['via' => 'google'], $context->ipAddress, $context->userAgent);

            return $this->tokenPairs->issue($client, $user->id, $user->role, $client->allowedScopes, $restored);
        }

        $existingByEmail = $this->users->findByEmail($email);

        if ($existingByEmail instanceof User) {
            $this->identities->insert(UserIdentity::link($existingByEmail->id, 'google', $claims->subject, $email));
            $restored = $this->accountRestorer->restoreIfTrashed($existingByEmail, $context);
            $this->audit->record(AuditEvent::LoginSucceeded, $existingByEmail->id, 'User', $existingByEmail->id, ['via' => 'google', 'linked' => true], $context->ipAddress, $context->userAgent);

            return $this->tokenPairs->issue($client, $existingByEmail->id, $existingByEmail->role, $client->allowedScopes, $restored);
        }

        // Senha inutilizável só pra ocupar o NOT NULL: a conta é social-only até um reset trocar por uma real.
        $newUser = User::register($claims->name, $email, null, bin2hex(random_bytes(32)), UserRole::Customer);
        $this->users->insert($newUser);
        $this->identities->insert(UserIdentity::link($newUser->id, 'google', $claims->subject, $email));
        $this->audit->record(AuditEvent::UserCreated, $newUser->id, 'User', $newUser->id, ['role' => $newUser->role->value, 'via' => 'google'], $context->ipAddress, $context->userAgent);

        return $this->tokenPairs->issue($client, $newUser->id, $newUser->role, $client->allowedScopes);
    }
}
