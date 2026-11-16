<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Auth;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Pidgey\Auth\AccountEdit;
use Pidgey\Auth\AccountEditOutcome;
use Pidgey\Auth\AccountEditResult;
use Pidgey\Auth\Passwords;
use Pidgey\Http\BadRequestException;
use Pidgey\Http\ConflictException;
use Pidgey\Http\HttpException;
use Pidgey\Http\LockedException;
use Pidgey\Http\UnauthorizedException;
use Pidgey\Infra\DatabaseConfig;
use Pidgey\Infra\Env;
use Pidgey\Infra\PdoFactory;
use Pidgey\Infra\Transaction;
use Pidgey\Model\AccountEditLockout;
use Pidgey\Model\Row;
use Pidgey\Model\User;
use Pidgey\Model\UserStatus;
use Pidgey\Repository\AccountEditLockoutRepository;
use Pidgey\Repository\PasswordResetTokenRepository;
use Pidgey\Repository\Sql;
use Pidgey\Repository\UserRepository;
use Pidgey\Tests\Support\AccountEditTestCase;

#[CoversClass(AccountEdit::class)]
#[CoversClass(AccountEditResult::class)]
#[CoversClass(AccountEditOutcome::class)]
#[UsesClass(AccountEditLockout::class)]
#[UsesClass(AccountEditLockoutRepository::class)]
#[UsesClass(BadRequestException::class)]
#[UsesClass(ConflictException::class)]
#[UsesClass(DatabaseConfig::class)]
#[UsesClass(Env::class)]
#[UsesClass(HttpException::class)]
#[UsesClass(LockedException::class)]
#[UsesClass(Passwords::class)]
#[UsesClass(PasswordResetTokenRepository::class)]
#[UsesClass(PdoFactory::class)]
#[UsesClass(Row::class)]
#[UsesClass(Sql::class)]
#[UsesClass(Transaction::class)]
#[UsesClass(UnauthorizedException::class)]
#[UsesClass(User::class)]
#[UsesClass(UserRepository::class)]
#[UsesClass(UserStatus::class)]
final class AccountEditSuccessTest extends AccountEditTestCase
{
    public function testEditsTheUsernameOnly(): void
    {
        $user = $this->user('bob', 'bob@example.com');

        $result = $this->service()->edit($user->id, self::$password, 'bobby', null, null);

        $this->assertEquals(new AccountEditResult('bobby', 'bob@example.com'), $result);
        $stored = $this->reload($user);
        $this->assertSame('bobby', $stored->username);
        $this->assertSame('bob@example.com', $stored->email);
        $this->assertSame(self::$digest, $stored->passwordDigest);
    }

    public function testEditsTheEmailOnly(): void
    {
        $user = $this->user('bob', 'bob@example.com');

        $result = $this->service()->edit($user->id, self::$password, null, 'bobby@example.com', null);

        $this->assertEquals(new AccountEditResult('bob', 'bobby@example.com'), $result);
        $this->assertSame('bobby@example.com', $this->reload($user)->email);
        $this->assertSame(self::$digest, $this->reload($user)->passwordDigest);
    }

    public function testEditsThePasswordOnly(): void
    {
        $user = $this->user('bob', 'bob@example.com');
        $newPassword = self::randomPassword('new-', 8);

        $result = $this->service()->edit($user->id, self::$password, null, null, $newPassword);

        $this->assertEquals(new AccountEditResult('bob', 'bob@example.com'), $result);
        $digest = $this->reload($user)->passwordDigest;
        $this->assertNotSame(self::$digest, $digest);
        $this->assertTrue(password_verify($newPassword, $digest));
        $this->assertFalse(password_verify(self::$password, $digest));
    }

    public function testEditsEveryFieldTogether(): void
    {
        $user = $this->user('bob', 'bob@example.com');
        $newPassword = self::randomPassword('new-', 8);

        $result = $this->service()->edit($user->id, self::$password, 'bobby', 'bobby@example.com', $newPassword);

        $this->assertEquals(new AccountEditResult('bobby', 'bobby@example.com'), $result);
        $stored = $this->reload($user);
        $this->assertSame('bobby', $stored->username);
        $this->assertSame('bobby@example.com', $stored->email);
        $this->assertTrue(password_verify($newPassword, $stored->passwordDigest));
    }

    public function testDoesNotRehashTheCurrentDigest(): void
    {
        $user = $this->user('bob', 'bob@example.com');
        $this->assertTrue((new Passwords())->needsRehash(self::$digest));

        $this->service()->edit($user->id, self::$password, 'bobby', null, null);

        $this->assertSame(self::$digest, $this->reload($user)->passwordDigest);
    }

    public function testACaseOnlyUsernameChangeIsAllowed(): void
    {
        $user = $this->user('Bob', 'Bob@example.com');

        $result = $this->service()->edit($user->id, self::$password, 'bob', 'bob@example.com', null);

        $this->assertEquals(new AccountEditResult('bob', 'bob@example.com'), $result);
        $this->assertSame('bob', $this->reload($user)->username);
        $this->assertSame('bob@example.com', $this->reload($user)->email);
    }

    public function testIdenticalValuesSkipTheCheckAndSucceed(): void
    {
        $user = $this->user('bob', 'bob@example.com');
        $token = $this->resetToken($user->id);

        $result = $this->service()->edit($user->id, self::$password, 'bob', 'bob@example.com', null);

        $this->assertEquals(new AccountEditResult('bob', 'bob@example.com'), $result);
        $this->assertNull($this->resetTokenUsedAt($token));
    }
}
