<?php

declare(strict_types=1);

namespace App\Domain\Shared\Ports;

use App\Domain\Shared\Trashable;

interface TrashableRepository
{
    /**
     * Tudo que está na lixeira e ainda não foi anonimizado -- quem decide o que já venceu é
     * `TrashState::allowsPurge()`, não o SQL.
     *
     * @return list<Trashable>
     */
    public function findTrashed(): array;

    /** Persiste uma entidade já anonimizada pelo domínio. */
    public function purge(Trashable $entity): void;
}
