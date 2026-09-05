<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Http\Middleware;

use App\Domain\Auth\Ports\TokenIssuer;
use App\Domain\Auth\ValueObjects\AccessTokenClaims;
use App\Domain\Exceptions\DomainErrorType;
use App\Domain\Exceptions\DomainException;
use App\Domain\Users\UserRole;
use App\Infrastructure\Database\PostgresConnection;
use App\Infrastructure\Http\JsonResponse;
use App\Infrastructure\Http\Middleware\AuthContextMiddleware;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\Router;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Teste de integração: abre transação real via autoschedule_app -- precisa
 * rodar dentro do compose (mesmo padrão dos outros testes de Postgres).
 */
final class AuthContextMiddlewareTest extends TestCase
{
    private PostgresConnection $connection;

    protected function setUp(): void
    {
        $this->connection = new PostgresConnection(
            driver: getenv('DB_DRIVER') ?: 'pgsql',
            host: getenv('DB_HOST') ?: '127.0.0.1',
            port: (int) (getenv('DB_PORT') ?: 5432),
            database: getenv('DB_DATABASE') ?: 'autoschedule',
            username: getenv('DB_APP_USERNAME') ?: 'autoschedule_app',
            password: getenv('DB_APP_PASSWORD') ?: 'changeme',
        );
    }

    #[Test]
    public function sem_bearer_e_rota_publica_comum_segue_direto_sem_transacao(): void
    {
        $router = new Router();
        $router->get('/api/me', static fn (): Response => new JsonResponse([]));
        $middleware = new AuthContextMiddleware(new FakeTokenIssuer(), $this->connection, $router);
        $inTransaction = null;

        $response = $middleware->handle(
            new Request(method: 'GET', path: '/api/me'),
            function (Request $request) use (&$inTransaction): Response {
                $inTransaction = $this->connection->pdo()->inTransaction();
                $this->assertNull($request->attribute('auth'));

                return new JsonResponse(['ok' => true]);
            },
        );

        $this->assertFalse($inTransaction);
        $this->assertSame('{"ok":true}', $response->body());
    }

    #[Test]
    public function com_bearer_valido_anexa_claims_e_seta_o_contexto_do_rls(): void
    {
        $claims = AccessTokenClaims::issue('11111111-1111-4111-8111-111111111111', 'autoschedule-web', UserRole::Customer, [], 900);
        $middleware = new AuthContextMiddleware(new FakeTokenIssuer(['valid-token' => $claims]), $this->connection, new Router());
        $seenUserId = null;
        $seenRole = null;

        $middleware->handle(
            new Request(method: 'GET', path: '/api/me', headers: ['authorization' => 'Bearer valid-token']),
            function (Request $request) use (&$seenUserId, &$seenRole, $claims): Response {
                $pdo = $this->connection->pdo();
                $seenUserId = $pdo->query("SELECT current_setting('app.current_user_id', true)")->fetchColumn();
                $seenRole = $pdo->query("SELECT current_setting('app.current_user_role', true)")->fetchColumn();

                $this->assertSame($claims, $request->attribute('auth'));

                return new JsonResponse(['ok' => true]);
            },
        );

        $this->assertSame('11111111-1111-4111-8111-111111111111', $seenUserId);
        $this->assertSame('customer', $seenRole);
    }

    #[Test]
    public function com_bearer_invalido_lanca_excecao_e_nao_chama_next(): void
    {
        $middleware = new AuthContextMiddleware(new FakeTokenIssuer(), $this->connection, new Router());
        $nextCalled = false;

        try {
            $middleware->handle(
                new Request(method: 'GET', path: '/api/me', headers: ['authorization' => 'Bearer garbage']),
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
    public function sem_bearer_em_rota_service_context_seta_o_contexto_de_servico(): void
    {
        $router = new Router();
        $router->post('/api/oauth/token', static fn (): Response => new JsonResponse([]), serviceContext: true);
        $middleware = new AuthContextMiddleware(new FakeTokenIssuer(), $this->connection, $router);
        $seenContext = null;

        $middleware->handle(
            new Request(method: 'POST', path: '/api/oauth/token'),
            function (Request $request) use (&$seenContext): Response {
                $seenContext = $this->connection->pdo()
                    ->query("SELECT current_setting('app.is_service_context', true)")
                    ->fetchColumn();

                return new JsonResponse(['ok' => true]);
            },
        );

        $this->assertSame('true', $seenContext);
    }
}

final class FakeTokenIssuer implements TokenIssuer
{
    /** @param array<string, AccessTokenClaims> $tokens */
    public function __construct(private readonly array $tokens = [])
    {
    }

    public function issueAccessToken(AccessTokenClaims $claims): string
    {
        throw new \LogicException('Not used in this test.');
    }

    public function decodeAccessToken(string $token): AccessTokenClaims
    {
        return $this->tokens[$token] ?? throw new DomainException('Invalid or expired access token.', DomainErrorType::Unauthorized);
    }
}
