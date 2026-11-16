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
final class AccountEditResetTokensTest extends AccountEditTestCase
{
    public function testAnEmailChangeMarksEveryUnusedResetTokenUsed(): void
    {
        $user = $this->user('bob', 'bob@example.com');
        $first = $this->resetToken($user->id);
        $second = $this->resetToken($user->id);
        $other = $this->resetToken($this->user('carol', 'carol@example.com')->id);

        $this->service()->edit($user->id, self::$password, null, 'bobby@example.com', null);

        $this->assertSame(self::NOW, $this->resetTokenUsedAt($first));
        $this->assertSame(self::NOW, $this->resetTokenUsedAt($second));
        $this->assertNull($this->resetTokenUsedAt($other));
    }

    public function testAPasswordChangeMarksEveryUnusedResetTokenUsed(): void
    {
        $user = $this->user('bob', 'bob@example.com');
        $token = $this->resetToken($user->id);

        $this->service()->edit($user->id, self::$password, null, null, 'new-' . bin2hex(random_bytes(8)));

        $this->assertSame(self::NOW, $this->resetTokenUsedAt($token));
    }

    public function testAUsernameOnlyChangeLeavesResetTokensUnused(): void
    {
        $user = $this->user('bob', 'bob@example.com');
        $token = $this->resetToken($user->id);

        $this->service()->edit($user->id, self::$password, 'bobby', null, null);

        $this->assertNull($this->resetTokenUsedAt($token));
    }

    public function testAFailureLeavesResetTokensUnused(): void
    {
        $user = $this->user('bob', 'bob@example.com');
        $token = $this->resetToken($user->id);

        $this->wrongPassword($user);

        $this->assertNull($this->resetTokenUsedAt($token));
    }
}
