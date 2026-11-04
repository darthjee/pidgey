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
use Pidgey\Auth\RotationLost;
use Pidgey\Auth\SecretKeys;
use Pidgey\Auth\SessionRotation;
use Pidgey\Auth\SessionTokens;
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
 * Runs without an outer test transaction, because the rotation opens its own; every table is
 * emptied before and after each test instead.
 */
#[CoversClass(SessionRotation::class)]
#[CoversClass(RotationLost::class)]
#[UsesClass(AccessToken::class)]
#[UsesClass(AccessTokenClaims::class)]
#[UsesClass(Base64Url::class)]
#[UsesClass(DatabaseConfig::class)]
#[UsesClass(Env::class)]
#[UsesClass(PdoFactory::class)]
#[UsesClass(RefreshToken::class)]
#[UsesClass(RefreshTokenRepository::class)]
#[UsesClass(RefreshTokenValue::class)]
#[UsesClass(RevokedReason::class)]
#[UsesClass(Row::class)]
#[UsesClass(SecretKeys::class)]
#[UsesClass(SessionTokens::class)]
#[UsesClass(Sql::class)]
#[UsesClass(Transaction::class)]
#[UsesClass(User::class)]
#[UsesClass(UserRepository::class)]
#[UsesClass(UserStatus::class)]
final class SessionRotationTest extends IntegrationTestCase
{
    private const KEY = 'session-rotation-test-secret-key-0123456789abcdef';

    private const NOW = 1_800_000_000;

    private const TTL = 604800;

    private const ISSUED = self::NOW - 3600;

    private const STARTED = self::NOW - 86400;

    private const EXPIRES = self::NOW + 86400;

    private const SESSION = '6f1c2a4e-8b3d-4c5f-9a7e-0d1b2c3e4f5a';

    private PDO $pdo;

    private Fixtures $fixtures;

    private RefreshTokenRepository $refreshTokens;

    private AccessToken $accessToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = self::pdo();
        DatabaseCleaner::truncateAll($this->pdo);
        $this->fixtures = new Fixtures($this->pdo);
        $this->refreshTokens = new RefreshTokenRepository($this->pdo);
        $this->accessToken = new AccessToken(new SecretKeys(self::KEY), 900, self::clockAt(self::NOW));
    }

    protected function tearDown(): void
    {
        DatabaseCleaner::truncateAll($this->pdo);

        parent::tearDown();
    }

    public function testRotationContinuesTheSessionWithANewToken(): void
    {
        $user = $this->user();
        $presented = $this->presented($user);

        $tokens = $this->rotation()->rotate($presented, $user, self::moment(self::NOW));

        $this->assertNotNull($tokens);
        $this->assertSame(self::SESSION, $tokens->sessionUuid);
        $this->assertSame($user, $tokens->user);
        $new = $this->refreshTokens->findByTokenHash(hash('sha256', $tokens->refreshToken()));
        $this->assertNotNull($new);
        $this->assertNotSame($presented->id, $new->id);
        $this->assertSame(self::SESSION, $new->sessionUuid);
        $this->assertSame(self::STARTED, $new->startedAt->getTimestamp());
        $this->assertSame(self::NOW, $new->issuedAt->getTimestamp());
        $this->assertSame(self::NOW + self::TTL, $new->expiresAt->getTimestamp());
        $this->assertNull($new->revokedAt);
        $old = $this->refreshTokens->findById($presented->id);
        $this->assertSame(RevokedReason::Rotated, $old?->revokedReason);
        $this->assertSame(self::NOW, $old->revokedAt?->getTimestamp());
        $claims = $this->accessToken->verify($tokens->accessToken());
        $this->assertSame(self::SESSION, $claims?->sessionId);
        $this->assertSame($user->id, $claims->userId);
    }

    public function testALostRaceRollsBackWithoutSideEffect(): void
    {
        $user = $this->user();
        $presented = $this->presented($user);
        $this->fixtures->refreshToken(userId: $user->id, startedAt: self::at(self::STARTED));
        $this->pdo->prepare(
            "UPDATE refresh_tokens SET revoked_at = :at, revoked_reason = 'rotated' WHERE id = :id",
        )->execute(['at' => self::at(self::NOW - 1), 'id' => $presented->id]);
        $before = $this->rows();

        $this->assertNull($this->rotation()->rotate($presented, $user, self::moment(self::NOW)));

        $this->assertFalse($this->pdo->inTransaction());
        $this->assertSame($before, $this->rows());
    }

    public function testATokenThatExpiredInBetweenIsNotRotated(): void
    {
        $user = $this->user();
        $presented = $this->presented($user);
        $before = $this->rows();

        $this->assertNull($this->rotation()->rotate($presented, $user, self::moment(self::EXPIRES)));

        $this->assertFalse($this->pdo->inTransaction());
        $this->assertSame($before, $this->rows());
    }

    private function rotation(): SessionRotation
    {
        return new SessionRotation(
            $this->refreshTokens,
            new Transaction($this->pdo),
            $this->accessToken,
            self::TTL,
        );
    }

    private function user(): User
    {
        $id = $this->fixtures->user();

        return (new UserRepository($this->pdo))->findById($id) ?? self::fail('The fixture user is missing.');
    }

    private function presented(User $user): RefreshToken
    {
        $id = $this->fixtures->refreshToken(
            userId: $user->id,
            sessionUuid: self::SESSION,
            issuedAt: self::at(self::ISSUED),
            expiresAt: self::at(self::EXPIRES),
            startedAt: self::at(self::STARTED),
        );

        return $this->refreshTokens->findById($id) ?? self::fail('The fixture token is missing.');
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

    private static function at(int $timestamp): string
    {
        return gmdate('Y-m-d H:i:s', $timestamp);
    }

    private static function moment(int $timestamp): DateTimeImmutable
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->setTimestamp($timestamp);
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
