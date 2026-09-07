<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Http\Middleware;

use App\Domain\Auth\ValueObjects\AccessTokenClaims;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Domain\User\UserRole;
use App\Infrastructure\Database\PostgresConnection;
use App\Infrastructure\Http\HttpMethod;
use App\Infrastructure\Http\JsonResponse;
use App\Infrastructure\Http\Middleware\AuthContextMiddleware;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\Route;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;

#[Group('integration')]
final class AuthContextMiddlewareTest extends TestCase
{
    private PostgresConnection $connection;

    protected function setUp(): void
    {
        $this->connection = TestDatabase::connectAsApp();
    }

    #[Test]
    public function rota_publica_comum_segue_direto_sem_abrir_transacao(): void
    {
        $inTransaction = null;

        $response = $this->middleware()->handle(
            $this->request($this->route()),
            function (Request $request) use (&$inTransaction): Response {
                $inTransaction = $this->connection->pdo()->inTransaction();

                return new JsonResponse(['ok' => true]);
            },
        );

        $this->assertFalse($inTransaction);
        $this->assertSame('{"ok":true}', $response->body());
    }

    #[Test]
    public function com_claims_seta_identidade_e_role_no_rls(): void
    {
        $seenUserId = null;
        $seenRole = null;

        $this->middleware()->handle(
            $this->request($this->route())->withAttribute('auth', $this->claims(UserRole::Customer)),
            function (Request $request) use (&$seenUserId, &$seenRole): Response {
                $pdo = $this->connection->pdo();
                $seenUserId = $pdo->query("SELECT current_setting('app.current_user_id', true)")->fetchColumn();
                $seenRole = $pdo->query("SELECT current_setting('app.current_user_role', true)")->fetchColumn();

                return new JsonResponse([]);
            },
        );

        $this->assertSame('11111111-1111-4111-8111-111111111111', $seenUserId);
        $this->assertSame('customer', $seenRole);
    }

    /** O rate limit já contou a tentativa quando isto roda, então recusar aqui não abre buraco de cota. */
    #[Test]
    public function relanca_a_falha_de_token_guardada_pelo_authenticate(): void
    {
        $nextCalled = false;
        $failure = new DomainException('Invalid or expired access token.', DomainErrorType::Unauthorized);

        try {
            $this->middleware()->handle(
                $this->request($this->route())->withAttribute('auth_error', $failure),
                function (Request $request) use (&$nextCalled): Response {
                    $nextCalled = true;

                    return new JsonResponse([]);
                },
            );
            $this->fail('Expected a DomainException to be thrown.');
        } catch (DomainException $exception) {
            $this->assertSame(DomainErrorType::Unauthorized, $exception->type());
        }

        $this->assertFalse($nextCalled);
    }

    #[Test]
    public function rota_de_service_context_sem_claims_seta_o_contexto_de_servico(): void
    {
        $seen = null;

        $this->middleware()->handle(
            $this->request($this->route()->serviceContext()),
            function (Request $request) use (&$seen): Response {
                $seen = $this->connection->pdo()->query("SELECT current_setting('app.is_service_context', true)")->fetchColumn();

                return new JsonResponse([]);
            },
        );

        $this->assertSame('true', $seen);
    }

    #[Test]
    public function rota_de_leitura_publica_sem_claims_seta_a_flag_publica(): void
    {
        $seen = null;

        $this->middleware()->handle(
            $this->request($this->route()->publicRead()),
            function (Request $request) use (&$seen): Response {
                $seen = $this->connection->pdo()->query("SELECT current_setting('app.is_public_read', true)")->fetchColumn();

                return new JsonResponse([]);
            },
        );

        $this->assertSame('true', $seen);
    }

    /** As duas marcas ficam setadas ao mesmo tempo, que é o que deixa o seller autenticado ver o perfil público alheio. */
    #[Test]
    public function leitura_publica_com_claims_seta_os_dois_contextos_juntos(): void
    {
        $seenUserId = null;
        $seenPublicRead = null;

        $this->middleware()->handle(
            $this->request($this->route()->publicRead())->withAttribute('auth', $this->claims(UserRole::Seller)),
            function (Request $request) use (&$seenUserId, &$seenPublicRead): Response {
                $pdo = $this->connection->pdo();
                $seenUserId = $pdo->query("SELECT current_setting('app.current_user_id', true)")->fetchColumn();
                $seenPublicRead = $pdo->query("SELECT current_setting('app.is_public_read', true)")->fetchColumn();

                return new JsonResponse([]);
            },
        );

        $this->assertSame('11111111-1111-4111-8111-111111111111', $seenUserId);
        $this->assertSame('true', $seenPublicRead);
    }

    private function middleware(): AuthContextMiddleware
    {
        return new AuthContextMiddleware($this->connection);
    }

    private function route(): Route
    {
        return new Route(HttpMethod::Get, '/api/me', static fn (Request $request): Response => new JsonResponse([]));
    }

    private function request(Route $route): Request
    {
        return new Request(method: 'GET', path: $route->path)->withAttribute('route', $route);
    }

    private function claims(UserRole $role): AccessTokenClaims
    {
        return AccessTokenClaims::issue('11111111-1111-4111-8111-111111111111', 'autoschedule-web', $role, [], 900);
    }
}
