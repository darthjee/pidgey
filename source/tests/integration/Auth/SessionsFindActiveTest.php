<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Auth;

use PHPUnit\Framework\Attributes\CoversClass;
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
final class SessionsFindActiveTest extends SessionsTestCase
{
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
}
