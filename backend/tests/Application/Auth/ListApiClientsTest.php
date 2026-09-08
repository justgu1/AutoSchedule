<?php

declare(strict_types=1);

namespace Tests\Application\Auth;

use App\Application\Auth\ListApiClients;
use App\Application\Shared\ActorContext;
use App\Domain\Auth\OAuthClient;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Domain\User\UserRole;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\InMemoryOAuthClientRepository;

final class ListApiClientsTest extends TestCase
{
    #[Test]
    public function lista_so_os_clients_do_proprio_dono(): void
    {
        [, $mine] = OAuthClient::createForOwner('user-1', 'Minha integração');
        [, $someoneElses] = OAuthClient::createForOwner('user-2', 'Outra integração');
        $listApiClients = new ListApiClients(new InMemoryOAuthClientRepository($mine, $someoneElses));

        $ids = array_map(static fn (OAuthClient $c): string => $c->id, $listApiClients(new ActorContext('user-1', UserRole::Seller)));

        $this->assertSame([$mine->id], $ids);
    }

    #[Test]
    public function exige_autenticacao(): void
    {
        $listApiClients = new ListApiClients(new InMemoryOAuthClientRepository());

        try {
            $listApiClients(new ActorContext());
            $this->fail('Expected a DomainException to be thrown.');
        } catch (DomainException $exception) {
            $this->assertSame(DomainErrorType::Unauthorized, $exception->type());
        }
    }
}
