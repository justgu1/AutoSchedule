<?php

declare(strict_types=1);

namespace App\Domain\Users\Ports;

use App\Domain\Users\User;
use App\Domain\Users\UserRole;

interface UserRepository
{
    public function findById(string $id): ?User;

    public function findByEmail(string $email): ?User;

    public function existsByEmail(string $email): bool;

    public function insert(User $user): void;

    public function update(User $user): void;

    /** Anonymizes PII and marks the row permanently deleted (LGPD right to erasure, purge da lixeira). No-op if the user doesn't exist. */
    public function anonymizeAndSoftDelete(string $id): void;

    /** Move pra lixeira -- recuperável por até 30 dias (login de novo ou `restore()`). */
    public function trash(string $id): void;

    /** Restaura da lixeira -- controller já validou `isEligibleForRestore()` antes de chamar. */
    public function restore(string $id): void;

    /** @return list<User> usuários na lixeira há mais de `$graceDays`, ainda não anonimizados -- candidatos da rotina de purge. */
    public function findPurgeEligible(int $graceDays, \DateTimeImmutable $now): array;

    /** @return list<User> */
    public function findPage(int $limit, int $offset): array;

    /** Total de usuários não deletados -- base pro `meta.last_page` da paginação. */
    public function count(): int;

    /** Usado pra bloquear DELETE do último admin restante (409 Conflict) -- só conta admin ativo, um trashed não protege ninguém. */
    public function countByRole(UserRole $role): int;
}
