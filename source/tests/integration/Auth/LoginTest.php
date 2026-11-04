<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Auth;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use Pidgey\Auth\AccessToken;
use Pidgey\Auth\Login;
use Pidgey\Auth\Passwords;
use Pidgey\Auth\RefreshTokenValue;
use Pidgey\Auth\SecretKeys;
use Pidgey\Auth\SessionRotation;
use Pidgey\Auth\Sessions;
use Pidgey\Auth\SessionTokens;
use Pidgey\Auth\SessionUuid;
use Pidgey\Container;
use Pidgey\Http\ForbiddenException;
use Pidgey\Http\HttpException;
use Pidgey\Http\UnauthorizedException;
use Pidgey\Infra\DatabaseConfig;
use Pidgey\Infra\Env;
use Pidgey\Infra\PdoFactory;
use Pidgey\Infra\SystemWallClock;
use Pidgey\Infra\Transaction;
use Pidgey\Model\Row;
use Pidgey\Model\User;
use Pidgey\Model\UserStatus;
use Pidgey\Repository\RefreshTokenRepository;
use Pidgey\Repository\Sql;
use Pidgey\Repository\UserRepository;
use Pidgey\Tests\Support\SessionEndpointTestCase;

#[CoversClass(Login::class)]
#[UsesClass(AccessToken::class)]
#[UsesClass(Container::class)]
#[UsesClass(DatabaseConfig::class)]
#[UsesClass(Env::class)]
#[UsesClass(ForbiddenException::class)]
#[UsesClass(HttpException::class)]
#[UsesClass(Passwords::class)]
#[UsesClass(PdoFactory::class)]
#[UsesClass(RefreshTokenRepository::class)]
#[UsesClass(RefreshTokenValue::class)]
#[UsesClass(Row::class)]
#[UsesClass(SecretKeys::class)]
#[UsesClass(SessionRotation::class)]
#[UsesClass(Sessions::class)]
#[UsesClass(SessionTokens::class)]
#[UsesClass(SessionUuid::class)]
#[UsesClass(Sql::class)]
#[UsesClass(SystemWallClock::class)]
#[UsesClass(Transaction::class)]
#[UsesClass(UnauthorizedException::class)]
#[UsesClass(User::class)]
#[UsesClass(UserRepository::class)]
#[UsesClass(UserStatus::class)]
final class LoginTest extends SessionEndpointTestCase
{
    /** A password generated once for the whole class, so no password literal is hardcoded. */
    private static string $password;

    /** A current `PASSWORD_DEFAULT` digest of the password, hashed once for the whole class. */
    private static string $digest;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$password = 'pw-' . bin2hex(random_bytes(12));
        self::$digest = password_hash(self::$password, PASSWORD_DEFAULT);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function sessionStatuses(): array
    {
        return [
            'approved' => ['approved'],
            'pending' => ['pending'],
        ];
    }

    #[DataProvider('sessionStatuses')]
    public function testTheRightPasswordStartsANewSession(string $status): void
    {
        $id = $this->user(status: $status);

        $tokens = $this->login();

        $token = $this->row('refresh_tokens', 'user_id', $id);
        $this->assertSame($id, $tokens->user->id);
        $this->assertSame(RefreshTokenValue::hash($tokens->refreshToken()), $token['token_hash']);
        $this->assertSame($tokens->sessionUuid, $token['session_uuid']);
        $this->assertNull($token['revoked_at']);
        $this->assertSame(
            $id,
            (new AccessToken(new SecretKeys(self::SECRET_KEY), self::ACCESS_TOKEN_TTL, new SystemWallClock()))
                ->verify($tokens->accessToken())?->userId,
        );
    }

    public function testTheUsernameIsComparedThroughTheCollation(): void
    {
        $id = $this->user();

        $this->assertSame($id, $this->login('BÓB')->user->id);
    }

    public function testTheEmailIsNotAUsername(): void
    {
        $this->user();

        $this->assertUnauthorized(fn () => $this->login('bob@example.com'));
    }

    public function testAnUnknownUsernameIsUnauthorized(): void
    {
        $this->user();

        $this->assertUnauthorized(fn () => $this->login('alice'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function everyStatus(): array
    {
        return [
            'approved' => ['approved'],
            'pending' => ['pending'],
            'denied' => ['denied'],
        ];
    }

    #[DataProvider('everyStatus')]
    public function testAWrongPasswordIsUnauthorizedWhateverTheStatus(string $status): void
    {
        $this->user(status: $status);

        $this->assertUnauthorized(fn () => $this->login(password: 'wrong-password'));
    }

    public function testTheRightPasswordOfADeniedUserIsForbiddenWithoutASession(): void
    {
        $this->user(status: 'denied');

        try {
            $this->login();
            $this->fail('Expected a ForbiddenException.');
        } catch (ForbiddenException $exception) {
            $this->assertSame(403, $exception->status());
            $this->assertSame('USER_DENIED', $exception->errorCode());
            $this->assertSame('This account has been denied access', $exception->errorMessage());
        }

        $this->assertSame(0, $this->rowCount('refresh_tokens'));
    }

    public function testAnOutdatedDigestIsRehashed(): void
    {
        $old = password_hash(self::$password, PASSWORD_BCRYPT, ['cost' => 4]);
        $id = $this->user(digest: $old);

        $this->login();

        $digest = (string) $this->row('users', 'id', $id)['password_digest'];
        $this->assertNotSame($old, $digest);
        $this->assertFalse(password_needs_rehash($digest, PASSWORD_DEFAULT));
        $this->assertTrue(password_verify(self::$password, $digest));
    }

    public function testAnOutdatedDigestIsRehashedForAPasswordOfExactly72Bytes(): void
    {
        $password = str_repeat('a', 72);
        $old = password_hash($password, PASSWORD_BCRYPT, ['cost' => 4]);
        $id = $this->user(digest: $old);

        $this->login(password: $password);

        $digest = (string) $this->row('users', 'id', $id)['password_digest'];
        $this->assertNotSame($old, $digest);
        $this->assertTrue(password_verify($password, $digest));
    }

    public function testAPasswordOver72BytesLogsInWithoutARehash(): void
    {
        $password = str_repeat('a', 80);
        $old = password_hash($password, PASSWORD_BCRYPT, ['cost' => 4]);
        $id = $this->user(digest: $old);

        $tokens = $this->login(password: $password);

        $this->assertSame($id, $tokens->user->id);
        $this->assertSame($old, $this->row('users', 'id', $id)['password_digest']);
        $this->assertSame(1, $this->rowCount('refresh_tokens'));
    }

    public function testAPasswordWithANulByteLogsInWithoutARehash(): void
    {
        $old = password_hash(self::$password, PASSWORD_BCRYPT, ['cost' => 4]);
        $id = $this->user(digest: $old);

        // bcrypt stops at the NUL byte, so this matches the digest of the password.
        $tokens = $this->login(password: self::$password . "\0suffix");

        $this->assertSame($id, $tokens->user->id);
        $this->assertSame($old, $this->row('users', 'id', $id)['password_digest']);
        $this->assertSame(1, $this->rowCount('refresh_tokens'));
    }

    public function testACurrentDigestIsNotRewritten(): void
    {
        $id = $this->user();

        $this->login();

        $this->assertSame(self::$digest, $this->row('users', 'id', $id)['password_digest']);
    }

    public function testAWrongPasswordNeverRehashes(): void
    {
        $old = password_hash(self::$password, PASSWORD_BCRYPT, ['cost' => 4]);
        $id = $this->user(digest: $old);

        $this->assertUnauthorized(fn () => $this->login(password: 'wrong-password'));
        $this->assertSame($old, $this->row('users', 'id', $id)['password_digest']);
    }

    public function testADeniedUserIsNotRehashed(): void
    {
        $old = password_hash(self::$password, PASSWORD_BCRYPT, ['cost' => 4]);
        $id = $this->user(digest: $old, status: 'denied');

        $this->expectException(ForbiddenException::class);

        try {
            $this->login();
        } finally {
            $this->assertSame($old, $this->row('users', 'id', $id)['password_digest']);
        }
    }

    public function testTwoLoginsStartTwoSessions(): void
    {
        $this->user();

        $first = $this->login();
        $second = $this->login();

        $this->assertNotSame($first->sessionUuid, $second->sessionUuid);
        $this->assertNotSame($first->refreshToken(), $second->refreshToken());
        $this->assertSame(2, $this->rowCount('refresh_tokens'));
    }

    private function user(string $status = 'approved', ?string $digest = null): int
    {
        return $this->fixtures->user(
            username: 'bob',
            email: 'bob@example.com',
            passwordDigest: $digest ?? self::$digest,
            status: $status,
        );
    }

    private function login(string $username = 'bob', ?string $password = null): SessionTokens
    {
        return $this->container()->login()->login($username, $password ?? self::$password);
    }

    private function assertUnauthorized(callable $action): void
    {
        try {
            $action();
            $this->fail('Expected an UnauthorizedException.');
        } catch (UnauthorizedException $exception) {
            $this->assertSame(401, $exception->status());
            $this->assertNull($exception->errorCode());
            $this->assertSame('Invalid username or password', $exception->errorMessage());
        }

        $this->assertSame(0, $this->rowCount('refresh_tokens'));
    }
}
