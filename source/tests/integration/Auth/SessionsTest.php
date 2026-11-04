<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Auth;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Pidgey\Auth\AccessToken;
use Pidgey\Auth\AccessTokenClaims;
use Pidgey\Auth\Base64Url;
use Pidgey\Auth\RefreshTokenValue;
use Pidgey\Auth\SecretKeys;
use Pidgey\Auth\Sessions;
use Pidgey\Auth\SessionTokens;
use Pidgey\Auth\SessionUuid;
use Pidgey\Infra\DatabaseConfig;
use Pidgey\Infra\Env;
use Pidgey\Infra\PdoFactory;
use Pidgey\Infra\Transaction;
use Pidgey\Infra\WallClock;
use Pidgey\Model\RefreshToken;
use Pidgey\Model\Row;
use Pidgey\Model\User;
use Pidgey\Model\UserStatus;
use Pidgey\Repository\RefreshTokenRepository;
use Pidgey\Repository\Sql;
use Pidgey\Repository\UserRepository;
use Pidgey\Tests\Support\DatabaseCleaner;
use Pidgey\Tests\Support\Fixtures;
use Pidgey\Tests\Support\IntegrationTestCase;

/**
 * Runs without an outer test transaction, because the service opens its own; every table is
 * emptied before and after each test instead.
 */
#[CoversClass(Sessions::class)]
#[UsesClass(AccessToken::class)]
#[UsesClass(AccessTokenClaims::class)]
#[UsesClass(Base64Url::class)]
#[UsesClass(DatabaseConfig::class)]
#[UsesClass(Env::class)]
#[UsesClass(PdoFactory::class)]
#[UsesClass(RefreshToken::class)]
#[UsesClass(RefreshTokenRepository::class)]
#[UsesClass(RefreshTokenValue::class)]
#[UsesClass(Row::class)]
#[UsesClass(SecretKeys::class)]
#[UsesClass(SessionTokens::class)]
#[UsesClass(SessionUuid::class)]
#[UsesClass(Sql::class)]
#[UsesClass(Transaction::class)]
#[UsesClass(User::class)]
#[UsesClass(UserRepository::class)]
#[UsesClass(UserStatus::class)]
final class SessionsTest extends IntegrationTestCase
{
    private const KEY = 'sessions-test-secret-key-0123456789abcdef';

    private const NOW = 1_800_000_000;

    private const TTL = 604800;

    private PDO $pdo;

    private Fixtures $fixtures;

    private AccessToken $accessToken;

    private Sessions $sessions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = self::pdo();
        DatabaseCleaner::truncateAll($this->pdo);
        $this->fixtures = new Fixtures($this->pdo);
        $clock = self::clockAt(self::NOW);
        $this->accessToken = new AccessToken(new SecretKeys(self::KEY), 900, $clock);
        $this->sessions = new Sessions(
            new RefreshTokenRepository($this->pdo),
            new UserRepository($this->pdo),
            new Transaction($this->pdo),
            $this->accessToken,
            $clock,
            self::TTL,
        );
    }

    protected function tearDown(): void
    {
        DatabaseCleaner::truncateAll($this->pdo);

        parent::tearDown();
    }

    public function testStartStoresTheHashNeverThePlainToken(): void
    {
        $tokens = $this->sessions->start($this->user());
        $row = $this->onlyRow();

        $this->assertMatchesRegularExpression('/\A[0-9a-f]{96}\z/', $tokens->refreshToken);
        $this->assertSame(hash('sha256', $tokens->refreshToken), $row['token_hash']);
        $this->assertNotContains($tokens->refreshToken, array_map('strval', $row));
    }

    public function testStartStoresTheSessionTimesAndUser(): void
    {
        $user = $this->user();

        $tokens = $this->sessions->start($user);
        $row = $this->onlyRow();

        $this->assertSame($user, $tokens->user);
        $this->assertSame($user->id, (int) $row['user_id']);
        $this->assertSame($tokens->sessionUuid, $row['session_uuid']);
        $this->assertMatchesRegularExpression(
            '/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/',
            $tokens->sessionUuid,
        );
        $this->assertSame(self::at(self::NOW), $row['issued_at']);
        $this->assertSame(self::at(self::NOW), $row['started_at']);
        $this->assertSame(self::at(self::NOW + self::TTL), $row['expires_at']);
        $this->assertNull($row['revoked_at']);
        $this->assertNull($row['revoked_reason']);
    }

    public function testStartIssuesAnAccessTokenForTheSession(): void
    {
        $user = $this->user();

        $tokens = $this->sessions->start($user);
        $claims = $this->accessToken->verify($tokens->accessToken);

        $this->assertNotNull($claims);
        $this->assertSame($user->id, $claims->userId);
        $this->assertSame($tokens->sessionUuid, $claims->sessionId);
    }

    public function testEachStartOpensADistinctSession(): void
    {
        $user = $this->user();

        $first = $this->sessions->start($user);
        $second = $this->sessions->start($user);

        $this->assertNotSame($first->sessionUuid, $second->sessionUuid);
        $this->assertNotSame($first->refreshToken, $second->refreshToken);
        $this->assertSame(2, $this->rowCount());
    }

    public function testStartRunsInsideTheCallersTransaction(): void
    {
        $user = $this->user();

        $tokens = (new Transaction($this->pdo))->run(fn (): SessionTokens => $this->sessions->start($user));

        $this->assertFalse($this->pdo->inTransaction());
        $this->assertSame($tokens->sessionUuid, $this->onlyRow()['session_uuid']);
    }

    private function user(): User
    {
        $id = $this->fixtures->user(username: 'alice');

        return (new UserRepository($this->pdo))->findById($id) ?? self::fail('The fixture user is missing.');
    }

    /**
     * @return array<string, mixed>
     */
    private function onlyRow(): array
    {
        $rows = $this->pdo->query('SELECT * FROM refresh_tokens')->fetchAll(PDO::FETCH_ASSOC);
        $this->assertCount(1, $rows);

        return $rows[0];
    }

    private function rowCount(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM refresh_tokens')->fetchColumn();
    }

    private static function at(int $timestamp): string
    {
        return gmdate('Y-m-d H:i:s', $timestamp);
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
