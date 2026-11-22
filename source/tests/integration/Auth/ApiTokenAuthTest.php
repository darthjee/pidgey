<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Auth;

use DateTimeImmutable;
use DateTimeZone;
use LogicException;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use Pidgey\Auth\ApiTokenAuth;
use Pidgey\Auth\ApiTokenValue;
use Pidgey\Auth\AuthenticatedApiToken;
use Pidgey\Auth\Caller;
use Pidgey\Container;
use Pidgey\Http\BaseMiddleware;
use Pidgey\Http\ErrorFormatter;
use Pidgey\Http\ErrorResponse;
use Pidgey\Http\HttpException;
use Pidgey\Http\JsonResponse;
use Pidgey\Http\ProcessingRequest;
use Pidgey\Http\Request;
use Pidgey\Http\Response;
use Pidgey\Http\UnauthorizedException;
use Pidgey\Infra\ConnectionFactory;
use Pidgey\Infra\DatabaseConfig;
use Pidgey\Infra\Env;
use Pidgey\Infra\LoggerFactory;
use Pidgey\Infra\PdoFactory;
use Pidgey\Infra\WallClock;
use Pidgey\Model\ApiToken;
use Pidgey\Model\Row;
use Pidgey\Repository\ApiTokenRepository;
use Pidgey\Tests\Support\CapturedLog;
use Pidgey\Tests\Support\Fixtures;
use Pidgey\Tests\Support\RepositoryTestCase;
use Pidgey\Tests\Support\StatementHookPdo;
use Throwable;

#[CoversClass(ApiTokenAuth::class)]
#[UsesClass(ApiToken::class)]
#[UsesClass(ApiTokenRepository::class)]
#[UsesClass(ApiTokenValue::class)]
#[UsesClass(AuthenticatedApiToken::class)]
#[UsesClass(BaseMiddleware::class)]
#[UsesClass(Container::class)]
#[UsesClass(DatabaseConfig::class)]
#[UsesClass(Env::class)]
#[UsesClass(ErrorFormatter::class)]
#[UsesClass(ErrorResponse::class)]
#[UsesClass(HttpException::class)]
#[UsesClass(JsonResponse::class)]
#[UsesClass(LoggerFactory::class)]
#[UsesClass(PdoFactory::class)]
#[UsesClass(ProcessingRequest::class)]
#[UsesClass(Request::class)]
#[UsesClass(Response::class)]
#[UsesClass(Row::class)]
#[UsesClass(UnauthorizedException::class)]
final class ApiTokenAuthTest extends RepositoryTestCase
{
    public const TIMESTAMP = '2026-11-22T12:00:00.000Z';

    private const PLAIN = 'pdg_0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

    public const NOW = '2026-11-22 12:00:00';

    private const UNIFORM_401 = '{"error":{"code":"UNAUTHORIZED","message":"Invalid or revoked API token"},'
        . '"statusCode":401,"timestamp":"' . self::TIMESTAMP . '"}';

    private WallClock $clock;

    private ApiTokenAuth $auth;

    private CapturedLog $log;

    protected function setUp(): void
    {
        parent::setUp();

        $this->log = new CapturedLog();
        $this->clock = new class implements WallClock {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable(ApiTokenAuthTest::NOW, new DateTimeZone('UTC'));
            }
        };
        $this->auth = new ApiTokenAuth(new ApiTokenRepository($this->pdo), $this->clock);
    }

    public function testAValidTokenAttachesTheCallerAndSetsLastUsedAt(): void
    {
        $organizationId = $this->fixtures->organization();
        $this->fixtures->user(organizationId: $organizationId);
        $id = $this->fixtures->apiToken(
            organizationId: $organizationId,
            tokenHash: ApiTokenValue::hash(self::PLAIN),
            scopes: ['send'],
        );
        $request = $this->processing(['Authorization' => 'Bearer ' . self::PLAIN]);

        $this->auth->processRequest($request);

        $this->assertEquals(
            new AuthenticatedApiToken($id, $organizationId, ['send']),
            $request->require(AuthenticatedApiToken::class),
        );
        $this->assertSame($request->require(AuthenticatedApiToken::class), $request->require(Caller::class));
        $this->assertFalse($request->hasResponse());
        $this->assertSame(self::NOW, $this->lastUsedAt($id));
        $this->assertSame([], $this->log->records());
    }

    public function testASecondUseMovesLastUsedAtForward(): void
    {
        $organizationId = $this->fixtures->organization();
        $this->fixtures->user(organizationId: $organizationId);
        $id = $this->fixtures->apiToken(
            organizationId: $organizationId,
            tokenHash: ApiTokenValue::hash(self::PLAIN),
            lastUsedAt: '2026-01-01 00:00:00',
        );

        $this->auth->processRequest($this->processing(['Authorization' => 'Bearer ' . self::PLAIN]));

        $this->assertSame(self::NOW, $this->lastUsedAt($id));
    }

    public function testACookieNextToAValidBearerIsIgnored(): void
    {
        $id = $this->liveToken();
        $request = $this->processing(['Authorization' => 'Bearer ' . self::PLAIN], ['access_token' => 'whatever']);

        $this->auth->processRequest($request);

        $this->assertSame($id, $request->require(AuthenticatedApiToken::class)->id);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function failures(): array
    {
        $cases = [
            'missing header',
            'basic scheme',
            'empty token',
            'extra spaces',
            'unknown token',
            'revoked token',
            'no approved user',
            'cookie only',
        ];

        return array_combine($cases, array_map(static fn (string $case): array => [$case], $cases));
    }

    #[DataProvider('failures')]
    public function testEveryFailureAnswersTheUniform401(string $case): void
    {
        $id = $this->fixturesFor($case);

        $response = $this->formatFailure(fn () => $this->auth->processRequest($this->requestFor($case)));

        $this->assertSame(401, $response->status());
        $this->assertSame(['Content-Type: application/json'], $response->headers());
        $this->assertSame(self::UNIFORM_401, $response->body());
        $this->assertNull($this->lastUsedAt($id));
        $this->assertSame([], $this->log->records());
    }

    public function testFailuresThrowTheSameException(): void
    {
        $errors = [];

        foreach (['missing header', 'unknown token', 'revoked token', 'no approved user'] as $case) {
            $this->pdo->exec('DELETE FROM api_tokens');
            $this->fixturesFor($case);

            try {
                $this->auth->processRequest($this->requestFor($case));
            } catch (UnauthorizedException $error) {
                $errors[] = [$error::class, $error->status(), $error->errorCode(), $error->errorMessage()];
            }
        }

        $this->assertSame(
            array_fill(0, 4, [UnauthorizedException::class, 401, null, ApiTokenAuth::UNAUTHORIZED_MESSAGE]),
            $errors,
        );
    }

    public function testAnUnapprovedOrganizationLeavesTheTokenUntouchedAndItWorksOnceApproved(): void
    {
        $organizationId = $this->fixtures->organization();
        $userId = $this->fixtures->user(organizationId: $organizationId, status: 'pending');
        $id = $this->fixtures->apiToken(organizationId: $organizationId, tokenHash: ApiTokenValue::hash(self::PLAIN));

        $this->formatFailure(
            fn () => $this->auth->processRequest($this->processing(['Authorization' => 'Bearer ' . self::PLAIN])),
        );

        $this->assertNull($this->column($id, 'revoked_at'));
        $this->assertNull($this->lastUsedAt($id));

        $this->pdo->prepare("UPDATE users SET status = 'approved' WHERE id = :id")->execute(['id' => $userId]);
        $request = $this->processing(['Authorization' => 'Bearer ' . self::PLAIN]);
        $this->auth->processRequest($request);

        $this->assertSame($id, $request->require(AuthenticatedApiToken::class)->id);
    }

    public function testAnotherOrganizationsApprovedUserDoesNotCount(): void
    {
        $this->fixtures->user(status: 'approved');
        $this->fixtures->apiToken(tokenHash: ApiTokenValue::hash(self::PLAIN));

        $response = $this->formatFailure(
            fn () => $this->auth->processRequest($this->processing(['Authorization' => 'Bearer ' . self::PLAIN])),
        );

        $this->assertSame(401, $response->status());
    }

    public function testADatabaseErrorPropagatesAndAnswers500(): void
    {
        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willThrowException(new PDOException('connection lost'));
        $auth = new ApiTokenAuth(new ApiTokenRepository($pdo), $this->clock);

        try {
            $auth->processRequest($this->processing(['Authorization' => 'Bearer ' . self::PLAIN]));
            $this->fail('Expected a PDOException');
        } catch (PDOException $error) {
            $response = $this->formatter()->format($error);
        }

        $this->assertSame(500, $response->status());
        $this->assertSame('INTERNAL_ERROR', json_decode($response->body(), true)['error']['code']);
    }

    public function testAFailedLastUsedUpdateIsNotSwallowed(): void
    {
        $this->pdo->rollBack();
        $config = DatabaseConfig::fromEnv();
        $factory = new PdoFactory();
        $hooked = new StatementHookPdo(
            $factory->dsn($config, true),
            $config->user,
            $config->password,
            $factory->options(),
        );
        $hooked->beginTransaction();
        $this->pdo = $hooked;
        $this->fixtures = new Fixtures($hooked);
        $this->liveToken();
        $hooked->beforeNext('UPDATE api_tokens', static fn () => throw new PDOException('update failed'));
        $auth = new ApiTokenAuth(new ApiTokenRepository($hooked), $this->clock);

        $this->expectException(PDOException::class);
        $this->expectExceptionMessage('update failed');

        $auth->processRequest($this->processing(['Authorization' => 'Bearer ' . self::PLAIN]));
    }

    public function testBuildTakesItsDependenciesFromTheContainer(): void
    {
        $connections = $this->createMock(ConnectionFactory::class);
        $connections->expects($this->once())->method('create')->willReturn($this->pdo);
        $id = $this->liveToken();
        $request = $this->processing(['Authorization' => 'Bearer ' . self::PLAIN]);

        ApiTokenAuth::build(new Container($connections, $this->clock))->processRequest($request);

        $this->assertSame($id, $request->require(AuthenticatedApiToken::class)->id);
    }

    private function liveToken(): int
    {
        $organizationId = $this->fixtures->organization();
        $this->fixtures->user(organizationId: $organizationId);

        return $this->fixtures->apiToken(organizationId: $organizationId, tokenHash: ApiTokenValue::hash(self::PLAIN));
    }

    /**
     * Inserts the rows a failing case needs and returns the id of the token to watch.
     */
    private function fixturesFor(string $case): int
    {
        $organizationId = $this->fixtures->organization();
        $this->fixtures->user(
            organizationId: $organizationId,
            status: $case === 'no approved user' ? 'pending' : 'approved',
        );

        return $this->fixtures->apiToken(
            organizationId: $organizationId,
            tokenHash: $case === 'unknown token' ? ApiTokenValue::hash('pdg_other') : ApiTokenValue::hash(self::PLAIN),
            revokedAt: $case === 'revoked token' ? '2026-01-01 00:00:00' : null,
        );
    }

    private function requestFor(string $case): ProcessingRequest
    {
        return match ($case) {
            'missing header' => $this->processing([]),
            'basic scheme' => $this->processing(['Authorization' => 'Basic ' . base64_encode('a:b')]),
            'empty token' => $this->processing(['Authorization' => 'Bearer ']),
            'extra spaces' => $this->processing(['Authorization' => 'Bearer  ' . self::PLAIN]),
            'cookie only' => $this->processing([], ['access_token' => self::PLAIN]),
            'unknown token', 'revoked token', 'no approved user' => $this->processing(
                ['Authorization' => 'Bearer ' . self::PLAIN],
            ),
            default => throw new LogicException("Unknown case {$case}"),
        };
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed>  $cookies
     */
    private function processing(array $headers, array $cookies = []): ProcessingRequest
    {
        return new ProcessingRequest(new Request([
            'method' => 'POST',
            'uri' => '/v1/messages',
            'headers' => $headers,
            'cookies' => $cookies,
        ]));
    }

    private function lastUsedAt(int $id): ?string
    {
        return $this->column($id, 'last_used_at');
    }

    private function column(int $id, string $column): ?string
    {
        $statement = $this->pdo->prepare("SELECT {$column} FROM api_tokens WHERE id = :id");
        $statement->execute(['id' => $id]);
        $value = $statement->fetchColumn();

        return $value === false || $value === null ? null : (string) $value;
    }

    private function formatFailure(callable $action): Response
    {
        try {
            $action();
        } catch (Throwable $error) {
            return $this->formatter()->format($error);
        }

        $this->fail('Expected an exception');
    }

    private function formatter(): ErrorFormatter
    {
        $clock = new class implements WallClock {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable(ApiTokenAuthTest::TIMESTAMP);
            }
        };

        return new ErrorFormatter($clock, $this->log->logger());
    }
}
