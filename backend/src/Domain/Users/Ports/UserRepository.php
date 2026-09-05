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

    /** Anonymizes PII and soft-deletes the row (LGPD right to erasure). No-op if the user doesn't exist. */
    public function anonymizeAndSoftDelete(string $id): void;

    /** @return list<User> */
    public function findPage(int $limit, int $offset): array;

    /** Total de usuários não deletados -- base pro `meta.last_page` da paginação. */
    public function count(): int;

    /** Usado pra bloquear DELETE do último admin restante (409 Conflict). */
    public function countByRole(UserRole $role): int;
}
