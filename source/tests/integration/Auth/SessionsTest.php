<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Auth;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use Pidgey\Auth\AccessToken;
use Pidgey\Auth\AccessTokenClaims;
use Pidgey\Auth\ActiveSession;
use Pidgey\Auth\Base64Url;
use Pidgey\Auth\RefreshTokenValue;
use Pidgey\Auth\RotationLost;
use Pidgey\Auth\SecretKeys;
use Pidgey\Auth\SessionRotation;
use Pidgey\Auth\Sessions;
use Pidgey\Auth\SessionTokens;
use Pidgey\Auth\SessionUuid;
use Pidgey\Infra\DatabaseConfig;
use Pidgey\Infra\Env;
use Pidgey\Infra\PdoFactory;
use Pidgey\Infra\Transaction;
use Pidgey\Infra\WallClock;
use Pidgey\Model\RefreshToken;
use Pidgey\Model\RevokedReason;
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
#[UsesClass(ActiveSession::class)]
#[UsesClass(Base64Url::class)]
#[UsesClass(DatabaseConfig::class)]
#[UsesClass(Env::class)]
#[UsesClass(PdoFactory::class)]
#[UsesClass(RefreshToken::class)]
#[UsesClass(RefreshTokenRepository::class)]
#[UsesClass(RefreshTokenValue::class)]
#[UsesClass(RevokedReason::class)]
#[UsesClass(Row::class)]
#[UsesClass(RotationLost::class)]
#[UsesClass(SecretKeys::class)]
#[UsesClass(SessionRotation::class)]
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

    private const ISSUED = self::NOW - 3600;

    private const STARTED = self::NOW - 86400;

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
        $refreshTokens = new RefreshTokenRepository($this->pdo);
        $transaction = new Transaction($this->pdo);
        $this->sessions = new Sessions(
            $refreshTokens,
            new UserRepository($this->pdo),
            new SessionRotation($refreshTokens, $transaction, $this->accessToken, self::TTL),
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

        $this->assertMatchesRegularExpression('/\A[0-9a-f]{96}\z/', $tokens->refreshToken());
        $this->assertSame(hash('sha256', $tokens->refreshToken()), $row['token_hash']);
        $this->assertNotContains($tokens->refreshToken(), array_map('strval', $row));
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
        $claims = $this->accessToken->verify($tokens->accessToken());

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
        $this->assertNotSame($first->refreshToken(), $second->refreshToken());
        $this->assertSame(2, $this->rowCount());
    }

    public function testStartRunsInsideTheCallersTransaction(): void
    {
        $user = $this->user();

        $tokens = (new Transaction($this->pdo))->run(fn (): SessionTokens => $this->sessions->start($user));

        $this->assertFalse($this->pdo->inTransaction());
        $this->assertSame($tokens->sessionUuid, $this->onlyRow()['session_uuid']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function liveStatuses(): array
    {
        return ['approved' => ['approved'], 'pending' => ['pending']];
    }

    #[DataProvider('liveStatuses')]
    public function testRefreshRotatesTheTokenOfALiveUser(string $status): void
    {
        $user = $this->user($status);
        $session = '6f1c2a4e-8b3d-4c5f-9a7e-0d1b2c3e4f5a';
        $presented = $this->token($user->id, 'presented', sessionUuid: $session);

        $tokens = $this->sessions->refresh('presented');

        $this->assertNotNull($tokens);
        $this->assertSame($session, $tokens->sessionUuid);
        $this->assertSame($user->id, $tokens->user->id);
        $this->assertSame($session, $this->accessToken->verify($tokens->accessToken())?->sessionId);
        $new = $this->rowByHash(hash('sha256', $tokens->refreshToken()));
        $this->assertSame($session, $new['session_uuid']);
        $this->assertSame(self::at(self::STARTED), $new['started_at']);
        $this->assertSame(self::at(self::NOW), $new['issued_at']);
        $this->assertSame(self::at(self::NOW + self::TTL), $new['expires_at']);
        $this->assertNull($new['revoked_reason']);
        $old = $this->rows()[$presented];
        $this->assertSame('rotated', $old['revoked_reason']);
        $this->assertSame(self::at(self::NOW), $old['revoked_at']);
    }

    public function testRefreshReadsTheClockOnceForTheWholeRotation(): void
    {
        $user = $this->user();
        $presented = $this->token($user->id, 'presented');
        $refreshTokens = new RefreshTokenRepository($this->pdo);
        $sessions = new Sessions(
            $refreshTokens,
            new UserRepository($this->pdo),
            new SessionRotation($refreshTokens, new Transaction($this->pdo), $this->accessToken, self::TTL),
            $this->accessToken,
            self::tickingClockFrom(self::NOW),
            self::TTL,
        );

        $tokens = $sessions->refresh('presented');

        $this->assertNotNull($tokens);
        $new = $this->rowByHash(hash('sha256', $tokens->refreshToken()));
        $this->assertSame(self::at(self::NOW), $new['issued_at']);
        $this->assertSame(self::at(self::NOW + self::TTL), $new['expires_at']);
        $this->assertSame(self::at(self::NOW), $this->rows()[$presented]['revoked_at']);
    }

    public function testRefreshWithAnUnknownTokenFails(): void
    {
        $this->token($this->user()->id, 'known');
        $before = $this->rows();

        $this->assertNull($this->sessions->refresh('unknown'));
        $this->assertSame($before, $this->rows());
    }

    public function testRefreshWithAnExpiredTokenFails(): void
    {
        $this->token($this->user()->id, 'expired', expiresAt: self::NOW);
        $before = $this->rows();

        $this->assertNull($this->sessions->refresh('expired'));
        $this->assertSame($before, $this->rows());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonRotatedReasons(): array
    {
        return [
            'logout' => ['logout'],
            'password_reset' => ['password_reset'],
            'admin_denied' => ['admin_denied'],
            'admin_edit' => ['admin_edit'],
            'replay_detected' => ['replay_detected'],
        ];
    }

    #[DataProvider('nonRotatedReasons')]
    public function testRefreshWithATokenRevokedForAnotherReasonFailsWithoutSideEffect(string $reason): void
    {
        $user = $this->user();
        $this->token($user->id, 'revoked', revokedReason: $reason);
        $this->token($user->id, 'other-session');
        $before = $this->rows();

        $this->assertNull($this->sessions->refresh('revoked'));
        $this->assertSame($before, $this->rows());
    }

    public function testReplayingARotatedTokenRevokesEveryActiveTokenOfTheUser(): void
    {
        $user = $this->user();
        $other = $this->user();
        $replayed = $this->token($user->id, 'replayed', revokedReason: 'rotated');
        $first = $this->token($user->id, 'first-session');
        $second = $this->token($user->id, 'second-session');
        $loggedOut = $this->token($user->id, 'logged-out', revokedReason: 'logout');
        $othersToken = $this->token($other->id, 'other-user');
        $before = $this->rows();

        $this->assertNull($this->sessions->refresh('replayed'));

        $after = $this->rows();
        foreach ([$first, $second] as $id) {
            $this->assertSame('replay_detected', $after[$id]['revoked_reason']);
            $this->assertSame(self::at(self::NOW), $after[$id]['revoked_at']);
        }
        $this->assertSame($before[$replayed], $after[$replayed]);
        $this->assertSame($before[$loggedOut], $after[$loggedOut]);
        $this->assertSame($before[$othersToken], $after[$othersToken]);
        $this->assertCount(count($before), $after);
    }

    public function testRefreshOfADeniedUserRevokesOnlyThePresentedToken(): void
    {
        $user = $this->user('denied');
        $presented = $this->token($user->id, 'presented');
        $otherSession = $this->token($user->id, 'other-session');
        $before = $this->rows();

        $this->assertNull($this->sessions->refresh('presented'));

        $after = $this->rows();
        $this->assertSame('admin_denied', $after[$presented]['revoked_reason']);
        $this->assertSame(self::at(self::NOW), $after[$presented]['revoked_at']);
        $this->assertSame($before[$otherSession], $after[$otherSession]);
        $this->assertCount(count($before), $after);
    }

    public function testRefreshFailsWhenTheUserRowIsMissing(): void
    {
        $user = $this->user();
        $this->token($user->id, 'orphan');
        $this->deleteUser($user->id);
        $before = $this->rows();

        $this->assertNull($this->sessions->refresh('orphan'));
        $this->assertSame($before, $this->rows());
    }

    public function testARotatedChainDetectsTheReplayOfAnOldToken(): void
    {
        $user = $this->user();
        $this->token($user->id, 'first');

        $second = $this->sessions->refresh('first');
        $this->assertNotNull($second);
        $third = $this->sessions->refresh($second->refreshToken());
        $this->assertNotNull($third);

        $this->assertNull($this->sessions->refresh('first'));
        $latest = $this->rowByHash(hash('sha256', $third->refreshToken()));
        $this->assertSame('replay_detected', $latest['revoked_reason']);
        $this->assertNull($this->sessions->refresh($third->refreshToken()));
    }

    public function testLogoutRevokesOnlyThePresentedSession(): void
    {
        $user = $this->user();
        $session = '6f1c2a4e-8b3d-4c5f-9a7e-0d1b2c3e4f5a';
        $presented = $this->token($user->id, 'presented', sessionUuid: $session);
        $rotated = $this->token($user->id, 'rotated', sessionUuid: $session, revokedReason: 'rotated');
        $otherSession = $this->token($user->id, 'other-session');
        $before = $this->rows();

        $this->sessions->logout('presented');

        $after = $this->rows();
        $this->assertSame('logout', $after[$presented]['revoked_reason']);
        $this->assertSame(self::at(self::NOW), $after[$presented]['revoked_at']);
        $this->assertSame($before[$rotated], $after[$rotated]);
        $this->assertSame($before[$otherSession], $after[$otherSession]);
    }

    /**
     * @return array<string, array{int|null, string|null}>
     */
    public static function staleTokens(): array
    {
        return [
            'rotated' => [null, 'rotated'],
            'expired' => [self::NOW - 60, null],
        ];
    }

    #[DataProvider('staleTokens')]
    public function testLogoutWithAStaleTokenStillRevokesItsSession(?int $expiresAt, ?string $reason): void
    {
        $user = $this->user();
        $session = '6f1c2a4e-8b3d-4c5f-9a7e-0d1b2c3e4f5a';
        $this->token($user->id, 'stale', sessionUuid: $session, expiresAt: $expiresAt, revokedReason: $reason);
        $active = $this->token($user->id, 'active', sessionUuid: $session);
        $otherSession = $this->token($user->id, 'other-session');
        $before = $this->rows();

        $this->sessions->logout('stale');

        $after = $this->rows();
        $this->assertSame('logout', $after[$active]['revoked_reason']);
        $this->assertSame($before[$otherSession], $after[$otherSession]);
    }

    public function testLogoutWithAnUnknownTokenChangesNothing(): void
    {
        $this->token($this->user()->id, 'known');
        $before = $this->rows();

        $this->sessions->logout('unknown');

        $this->assertSame($before, $this->rows());
    }

    public function testRevokeAllForUserRevokesOnlyActiveRowsOfThatUser(): void
    {
        $user = $this->user();
        $first = $this->token($user->id, 'first');
        $second = $this->token($user->id, 'second');
        $loggedOut = $this->token($user->id, 'logged-out', revokedReason: 'logout');
        $othersToken = $this->token($this->user()->id, 'other-user');
        $before = $this->rows();

        $this->sessions->revokeAllForUser($user->id, RevokedReason::PasswordReset);

        $after = $this->rows();
        foreach ([$first, $second] as $id) {
            $this->assertSame('password_reset', $after[$id]['revoked_reason']);
            $this->assertSame(self::at(self::NOW), $after[$id]['revoked_at']);
        }
        $this->assertSame($before[$loggedOut], $after[$loggedOut]);
        $this->assertSame($before[$othersToken], $after[$othersToken]);
    }

    public function testFindActiveReturnsTheTokenAndItsUserWithoutChangingAnything(): void
    {
        $user = $this->user();
        $id = $this->token($user->id, 'active');
        $before = $this->rows();

        $session = $this->sessions->findActive('active');

        $this->assertNotNull($session);
        $this->assertSame($id, $session->token->id);
        $this->assertSame($user->id, $session->user->id);
        $this->assertSame($before, $this->rows());
    }

    public function testFindActiveIgnoresUnknownRevokedAndExpiredTokens(): void
    {
        $user = $this->user();
        $this->token($user->id, 'rotated', revokedReason: 'rotated');
        $this->token($user->id, 'logged-out', revokedReason: 'logout');
        $this->token($user->id, 'expired', expiresAt: self::NOW);
        $before = $this->rows();

        $this->assertNull($this->sessions->findActive('unknown'));
        $this->assertNull($this->sessions->findActive('rotated'));
        $this->assertNull($this->sessions->findActive('logged-out'));
        $this->assertNull($this->sessions->findActive('expired'));
        $this->assertSame($before, $this->rows());
    }

    public function testFindActiveIgnoresATokenWhoseUserIsMissing(): void
    {
        $user = $this->user();
        $this->token($user->id, 'orphan');
        $this->deleteUser($user->id);

        $this->assertNull($this->sessions->findActive('orphan'));
    }

    public function testRevokeRevokesAnActiveTokenAndLeavesARevokedOneAlone(): void
    {
        $user = $this->user();
        $active = $this->token($user->id, 'active');
        $loggedOut = $this->token($user->id, 'logged-out', revokedReason: 'logout');
        $before = $this->rows();

        $this->sessions->revoke($this->entity($active), RevokedReason::AdminDenied);
        $this->sessions->revoke($this->entity($loggedOut), RevokedReason::AdminDenied);

        $after = $this->rows();
        $this->assertSame('admin_denied', $after[$active]['revoked_reason']);
        $this->assertSame(self::at(self::NOW), $after[$active]['revoked_at']);
        $this->assertSame($before[$loggedOut], $after[$loggedOut]);
    }

    private function entity(int $id): RefreshToken
    {
        return (new RefreshTokenRepository($this->pdo))->findById($id) ?? self::fail('The fixture token is missing.');
    }

    private function deleteUser(int $id): void
    {
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

        try {
            $this->pdo->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $id]);
        } finally {
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    private function user(string $status = 'approved'): User
    {
        $id = $this->fixtures->user(status: $status);

        return (new UserRepository($this->pdo))->findById($id) ?? self::fail('The fixture user is missing.');
    }

    private function token(
        int $userId,
        string $plain,
        ?string $sessionUuid = null,
        ?int $expiresAt = null,
        ?string $revokedReason = null,
    ): int {
        return $this->fixtures->refreshToken(
            userId: $userId,
            tokenHash: hash('sha256', $plain),
            sessionUuid: $sessionUuid,
            issuedAt: self::at(self::ISSUED),
            expiresAt: self::at($expiresAt ?? self::NOW + 86400),
            revokedAt: $revokedReason === null ? null : self::at(self::ISSUED + 60),
            revokedReason: $revokedReason,
            startedAt: self::at(self::STARTED),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rows(): array
    {
        $rows = [];

        $statement = $this->pdo->query('SELECT * FROM refresh_tokens ORDER BY id');

        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rows[(int) $row['id']] = $row;
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function rowByHash(string $tokenHash): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM refresh_tokens WHERE token_hash = :hash');
        $statement->execute(['hash' => $tokenHash]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($row);

        return $row;
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

    /**
     * A clock that moves one minute forward on every read, so a second read would show.
     */
    private static function tickingClockFrom(int $timestamp): WallClock
    {
        return new class ($timestamp) implements WallClock {
            public function __construct(private int $timestamp)
            {
            }

            public function now(): DateTimeImmutable
            {
                $now = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->setTimestamp($this->timestamp);
                $this->timestamp += 60;

                return $now;
            }
        };
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
