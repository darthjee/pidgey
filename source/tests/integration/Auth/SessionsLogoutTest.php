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
final class SessionsLogoutTest extends SessionsTestCase
{
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
}
