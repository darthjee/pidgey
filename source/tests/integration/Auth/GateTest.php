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
use Pidgey\Auth\AccessToken;
use Pidgey\Auth\AccessTokenClaims;
use Pidgey\Auth\AdminCheck;
use Pidgey\Auth\AuthenticatedUser;
use Pidgey\Auth\Base64Url;
use Pidgey\Auth\Caller;
use Pidgey\Auth\Gate;
use Pidgey\Auth\SecretKeys;
use Pidgey\Container;
use Pidgey\Http\BaseMiddleware;
use Pidgey\Http\ErrorFormatter;
use Pidgey\Http\ErrorResponse;
use Pidgey\Http\ForbiddenException;
use Pidgey\Http\HttpException;
use Pidgey\Http\JsonResponse;
use Pidgey\Http\ProcessingRequest;
use Pidgey\Http\Request;
use Pidgey\Http\RequestHandler;
use Pidgey\Http\Response;
use Pidgey\Http\UnauthorizedException;
use Pidgey\Infra\ConnectionFactory;
use Pidgey\Infra\DatabaseConfig;
use Pidgey\Infra\Env;
use Pidgey\Infra\LoggerFactory;
use Pidgey\Infra\PdoFactory;
use Pidgey\Infra\WallClock;
use Pidgey\Model\Row;
use Pidgey\Model\User;
use Pidgey\Model\UserStatus;
use Pidgey\Repository\Sql;
use Pidgey\Repository\UserRepository;
use Pidgey\Tests\Support\CapturedLog;
use Pidgey\Tests\Support\RepositoryTestCase;
use Pidgey\Tests\Support\StubHandler;
use Throwable;

#[CoversClass(Gate::class)]
#[UsesClass(AccessToken::class)]
#[UsesClass(AccessTokenClaims::class)]
#[UsesClass(AdminCheck::class)]
#[UsesClass(AuthenticatedUser::class)]
#[UsesClass(Base64Url::class)]
#[UsesClass(BaseMiddleware::class)]
#[UsesClass(Container::class)]
#[UsesClass(DatabaseConfig::class)]
#[UsesClass(Env::class)]
#[UsesClass(ErrorFormatter::class)]
#[UsesClass(ErrorResponse::class)]
#[UsesClass(ForbiddenException::class)]
#[UsesClass(HttpException::class)]
#[UsesClass(JsonResponse::class)]
#[UsesClass(LoggerFactory::class)]
#[UsesClass(PdoFactory::class)]
#[UsesClass(ProcessingRequest::class)]
#[UsesClass(Request::class)]
#[UsesClass(RequestHandler::class)]
#[UsesClass(Response::class)]
#[UsesClass(Row::class)]
#[UsesClass(SecretKeys::class)]
#[UsesClass(Sql::class)]
#[UsesClass(UnauthorizedException::class)]
#[UsesClass(User::class)]
#[UsesClass(UserRepository::class)]
#[UsesClass(UserStatus::class)]
final class GateTest extends RepositoryTestCase
{
    public const TIMESTAMP = '2026-10-25T12:00:00.000Z';

    private const KEY = 'gate-test-current-secret-key-0123456789abcdef';

    private const PREVIOUS_KEY = 'gate-test-previous-secret-key-0123456789abcdef';

    private const WRONG_KEY = 'gate-test-unknown-secret-key-0123456789abcdef';

    private const SESSION = '0b8f2c3e-1d4a-4b6c-9e7f-a1b2c3d4e5f6';

    private const OTHER_SESSION = '9a7e6d5c-4b3a-4f2e-8d1c-0b9a8f7e6d5c';

    private const UNIFORM_401 = '{"error":{"code":"UNAUTHORIZED","message":"Invalid or expired access token"},'
        . '"statusCode":401,"timestamp":"' . self::TIMESTAMP . '"}';

    private int $now;

    private WallClock $clock;

    private Gate $gate;

    private CapturedLog $log;

    protected function setUp(): void
    {
        parent::setUp();

        $this->log = new CapturedLog();
        $this->now = time();
        $this->clock = self::clockAt($this->now);
        $this->gate = new Gate($this->issuer(self::KEY), new UserRepository($this->pdo), $this->clock);
    }

    public function testAValidCallerIsAttachedWithTheDatabaseValues(): void
    {
        $organizationId = $this->fixtures->organization();
        $userId = $this->fixtures->user(organizationId: $organizationId, username: 'bob', isAdmin: false);
        $this->fixtures->refreshToken(userId: $userId, sessionUuid: self::SESSION);
        $jwt = $this->issuer(self::KEY)->issue($userId, self::SESSION, 'impostor', true);
        $request = $this->processing(['access_token' => $jwt]);

        $this->gate->processRequest($request);

        $this->assertEquals(
            new AuthenticatedUser($userId, self::SESSION, 'bob', false, $organizationId),
            $request->require(AuthenticatedUser::class),
        );
        $this->assertSame($request->require(AuthenticatedUser::class), $request->require(Caller::class));
        $this->assertFalse($request->hasResponse());
    }

    public function testATokenSignedWithAPreviousKeyStillPasses(): void
    {
        $userId = $this->liveUser();
        $gate = new Gate(
            new AccessToken(new SecretKeys(self::KEY, [self::PREVIOUS_KEY]), 900, $this->clock),
            new UserRepository($this->pdo),
            $this->clock,
        );
        $request = $this->processing(['access_token' => $this->issuer(self::PREVIOUS_KEY)->issue(
            $userId,
            self::SESSION,
            'bob',
            false,
        )]);

        $gate->processRequest($request);

        $this->assertSame($userId, $request->require(AuthenticatedUser::class)->id);
    }

    public function testABearerHeaderNextToAValidCookieIsIgnored(): void
    {
        $userId = $this->liveUser();
        $request = $this->processing(
            ['access_token' => $this->issuer(self::KEY)->issue($userId, self::SESSION, 'bob', false)],
            ['Authorization' => 'Bearer not-a-token'],
        );

        $this->gate->processRequest($request);

        $this->assertSame($userId, $request->require(AuthenticatedUser::class)->id);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function failures(): array
    {
        $cases = [
            'missing cookie',
            'empty cookie',
            'array cookie',
            'malformed token',
            'bad signature',
            'expired token',
            'revoked session',
            'expired session',
            'unknown sid',
            'sub mismatch',
            'pending user',
            'denied user',
            'deleted user',
            'bearer header only',
        ];

        return array_combine($cases, array_map(static fn (string $case): array => [$case], $cases));
    }

    #[DataProvider('failures')]
    public function testEveryFailureAnswersTheUniform401(string $case): void
    {
        $request = $this->failingRequest($case);

        $response = $this->formatFailure(fn () => $this->gate->processRequest($request));

        $this->assertSame(401, $response->status());
        $this->assertSame(['Content-Type: application/json'], $response->headers());
        $this->assertSame(self::UNIFORM_401, $response->body());
        $this->assertNull($request->attached(AuthenticatedUser::class));
        $this->assertSame([], $this->log->records());
    }

    public function testFailuresThrowTheSameException(): void
    {
        $errors = [];

        foreach (['missing cookie', 'bad signature', 'unknown sid'] as $case) {
            try {
                $this->gate->processRequest($this->failingRequest($case));
            } catch (UnauthorizedException $error) {
                $errors[] = [$error::class, $error->status(), $error->errorCode(), $error->errorMessage()];
            }
        }

        $this->assertSame(
            array_fill(0, 3, [UnauthorizedException::class, 401, null, Gate::UNAUTHORIZED_MESSAGE]),
            $errors,
        );
    }

    public function testADatabaseErrorPropagatesAndAnswers500(): void
    {
        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willThrowException(new PDOException('connection lost'));
        $gate = new Gate($this->issuer(self::KEY), new UserRepository($pdo), $this->clock);
        $request = $this->processing([
            'access_token' => $this->issuer(self::KEY)->issue(7, self::SESSION, 'bob', false),
        ]);

        try {
            $gate->processRequest($request);
            $this->fail('Expected a PDOException');
        } catch (PDOException $error) {
            $response = $this->formatter()->format($error);
        }

        $this->assertSame(500, $response->status());
        $this->assertSame('INTERNAL_ERROR', json_decode($response->body(), true)['error']['code']);
    }

    public function testAnAnonymousCallerOnAnAdminRouteGets401NotForbidden(): void
    {
        $request = $this->processing([]);

        $response = $this->formatFailure(
            fn () => (new StubHandler())->handle($request, [$this->gate, new AdminCheck()]),
        );

        $this->assertSame(self::UNIFORM_401, $response->body());
    }

    public function testANonAdminOnAnAdminRouteGets403(): void
    {
        $userId = $this->liveUser();
        $request = $this->processing([
            'access_token' => $this->issuer(self::KEY)->issue($userId, self::SESSION, 'bob', true),
        ]);

        $response = $this->formatFailure(
            fn () => (new StubHandler())->handle($request, [$this->gate, new AdminCheck()]),
        );

        $this->assertSame(403, $response->status());
        $this->assertSame(
            '{"error":{"code":"FORBIDDEN","message":"Admin access required"},'
            . '"statusCode":403,"timestamp":"' . self::TIMESTAMP . '"}',
            $response->body(),
        );
    }

    public function testAnAdminOnAnAdminRouteReachesTheHandler(): void
    {
        $userId = $this->liveUser(isAdmin: true);
        $request = $this->processing([
            'access_token' => $this->issuer(self::KEY)->issue($userId, self::SESSION, 'root', false),
        ]);

        $response = (new StubHandler())->handle($request, [$this->gate, new AdminCheck()]);

        $this->assertSame('stub', $response->body());
    }

    public function testBuildTakesItsDependenciesFromTheContainer(): void
    {
        $connections = $this->createMock(ConnectionFactory::class);
        $connections->expects($this->once())->method('create')->willReturn($this->pdo);
        $container = new Container($connections, $this->clock);
        $saved = getenv('SECRET_KEY');
        putenv('SECRET_KEY=' . self::KEY);

        try {
            $gate = Gate::build($container);
        } finally {
            putenv($saved === false ? 'SECRET_KEY' : "SECRET_KEY={$saved}");
        }

        $userId = $this->liveUser();
        $request = $this->processing([
            'access_token' => $this->issuer(self::KEY)->issue($userId, self::SESSION, 'bob', false),
        ]);
        $gate->processRequest($request);

        $this->assertSame($userId, $request->require(AuthenticatedUser::class)->id);
    }

    private function failingRequest(string $case): ProcessingRequest
    {
        return match ($case) {
            'missing cookie' => $this->processing([]),
            'empty cookie' => $this->processing(['access_token' => '']),
            'array cookie' => $this->processing(['access_token' => ['x']]),
            'malformed token' => $this->processing(['access_token' => 'not-a-jwt']),
            'bad signature' => $this->cookieFor($this->liveUser(), self::WRONG_KEY),
            'expired token' => $this->cookieFor($this->liveUser(), self::KEY, $this->now - 901),
            'revoked session' => $this->cookieFor($this->userWithSession(revokedAt: $this->at(-60))),
            'expired session' => $this->cookieFor($this->userWithSession(expiresAt: $this->at(0))),
            'unknown sid' => $this->cookieFor($this->liveUser(), self::KEY, $this->now, self::OTHER_SESSION),
            'sub mismatch' => $this->subMismatch(),
            'pending user' => $this->cookieFor($this->userWithSession(status: 'pending')),
            'denied user' => $this->cookieFor($this->userWithSession(status: 'denied')),
            'deleted user' => $this->cookieFor($this->deletedUser()),
            'bearer header only' => $this->bearerOnly(),
            default => throw new LogicException("Unknown case {$case}"),
        };
    }

    private function bearerOnly(): ProcessingRequest
    {
        $jwt = $this->issuer(self::KEY)->issue($this->liveUser(), self::SESSION, 'bob', false);

        return $this->processing([], ['Authorization' => 'Bearer ' . $jwt]);
    }

    private function subMismatch(): ProcessingRequest
    {
        $this->liveUser();
        $otherId = $this->fixtures->user();
        $this->fixtures->refreshToken(userId: $otherId, sessionUuid: self::OTHER_SESSION);

        return $this->cookieFor($otherId);
    }

    private function deletedUser(): int
    {
        $userId = $this->liveUser();
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

        try {
            $this->pdo->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $userId]);
        } finally {
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }

        return $userId;
    }

    private function liveUser(bool $isAdmin = false): int
    {
        return $this->userWithSession(isAdmin: $isAdmin);
    }

    private function userWithSession(
        string $status = 'approved',
        bool $isAdmin = false,
        ?string $expiresAt = null,
        ?string $revokedAt = null,
    ): int {
        $userId = $this->fixtures->user(isAdmin: $isAdmin, status: $status);
        $this->fixtures->refreshToken(
            userId: $userId,
            sessionUuid: self::SESSION,
            expiresAt: $expiresAt ?? $this->at(3600),
            revokedAt: $revokedAt,
            revokedReason: $revokedAt === null ? null : 'logout',
        );

        return $userId;
    }

    private function cookieFor(
        int $userId,
        string $key = self::KEY,
        ?int $issuedAt = null,
        string $sessionId = self::SESSION,
    ): ProcessingRequest {
        $issuer = $this->issuer($key, $issuedAt ?? $this->now);

        return $this->processing(['access_token' => $issuer->issue($userId, $sessionId, 'bob', false)]);
    }

    private function issuer(string $key, ?int $at = null): AccessToken
    {
        return new AccessToken(new SecretKeys($key), 900, $at === null ? $this->clock : self::clockAt($at));
    }

    /**
     * @param array<string, mixed>  $cookies
     * @param array<string, string> $headers
     */
    private function processing(array $cookies, array $headers = []): ProcessingRequest
    {
        return new ProcessingRequest(new Request([
            'method' => 'GET',
            'uri' => '/auth/me.json',
            'headers' => $headers,
            'cookies' => $cookies,
        ]));
    }

    private function at(int $offset): string
    {
        return gmdate('Y-m-d H:i:s', $this->now + $offset);
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
                return new DateTimeImmutable(GateTest::TIMESTAMP);
            }
        };

        return new ErrorFormatter($clock, $this->log->logger());
    }

    private static function clockAt(int $timestamp): WallClock
    {
        return new class ($timestamp) implements WallClock {
            public function __construct(private readonly int $timestamp)
            {
            }

            public function now(): DateTimeImmutable
            {
                return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->setTimestamp($this->timestamp);
            }
        };
    }
}
