<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Auth;

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
use Pidgey\Model\RefreshToken;
use Pidgey\Model\RevokedReason;
use Pidgey\Model\Row;
use Pidgey\Model\User;
use Pidgey\Model\UserStatus;
use Pidgey\Repository\RefreshTokenRepository;
use Pidgey\Repository\Sql;
use Pidgey\Repository\UserRepository;
use Pidgey\Tests\Support\SessionsTestCase;
use Pidgey\Tests\Support\TickingWallClock;

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
final class SessionsRefreshTest extends SessionsTestCase
{
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
            new TickingWallClock(self::NOW),
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
}
