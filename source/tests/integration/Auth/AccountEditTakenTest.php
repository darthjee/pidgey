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
final class AccountEditTakenTest extends AccountEditTestCase
{
    public function testUsernameTakenByAnotherUserIsRefusedAndCounted(): void
    {
        $user = $this->user('bob', 'bob@example.com');
        $this->user('Carol', 'carol@example.com');

        $this->assertError(
            fn () => $this->service()->edit($user->id, self::$password, 'carol', 'bobby@example.com', null),
            409,
            'USERNAME_TAKEN',
            'Username already in use',
        );

        $this->assertUnchanged($user);
        $this->assertLockout($user->id, 1, null);
    }

    public function testEmailTakenByAnotherUserIsRefusedAndCounted(): void
    {
        $user = $this->user('bob', 'bob@example.com');
        $this->user('carol', 'carol@example.com');

        $this->assertError(
            fn () => $this->service()->edit($user->id, self::$password, 'bobby', 'CAROL@example.com', null),
            409,
            'EMAIL_TAKEN',
            'Email already in use',
        );

        $this->assertUnchanged($user);
        $this->assertLockout($user->id, 1, null);
    }

    public function testUsernameIsCheckedBeforeEmail(): void
    {
        $user = $this->user('bob', 'bob@example.com');
        $this->user('carol', 'carol@example.com');

        $this->assertError(
            fn () => $this->service()->edit($user->id, self::$password, 'carol', 'carol@example.com', null),
            409,
            'USERNAME_TAKEN',
            'Username already in use',
        );
    }

    public function testAUsernameCommittedAfterTheSnapshotIsRefusedAndCounted(): void
    {
        $user = $this->user('bob', 'bob@example.com');
        $this->commitConcurrently('Carol', 'carol@example.com');

        $this->assertError(
            fn () => $this->service()->edit($user->id, self::$password, 'carol', 'bobby@example.com', null),
            409,
            'USERNAME_TAKEN',
            'Username already in use',
        );

        $this->assertUnchanged($user);
        $this->assertLockout($user->id, 1, null);
    }

    public function testAnEmailCommittedAfterTheSnapshotIsRefusedAndCounted(): void
    {
        $user = $this->user('bob', 'bob@example.com');
        $this->commitConcurrently('carol', 'Carol@example.com');

        $this->assertError(
            fn () => $this->service()->edit($user->id, self::$password, 'bobby', 'carol@example.com', null),
            409,
            'EMAIL_TAKEN',
            'Email already in use',
        );

        $this->assertUnchanged($user);
        $this->assertLockout($user->id, 1, null);
    }

    public function testAUsernameRaceOnUpdateAppliesNothingAndIsCounted(): void
    {
        $user = $this->user('bob', 'bob@example.com');
        $token = $this->resetToken($user->id);
        $this->setLockout($user->id, 1);
        $this->commitConcurrently('carol', 'carol@example.com');

        $this->assertError(
            fn () => $this->service()->editSkippingTakenChecks(
                $user->id,
                self::$password,
                'Carol',
                'bobby@example.com',
                'new-' . bin2hex(random_bytes(8)),
            ),
            409,
            'USERNAME_TAKEN',
            'Username already in use',
        );

        $this->assertUnchanged($user);
        $this->assertNull($this->resetTokenUsedAt($token));
        $this->assertLockout($user->id, 2, null);
    }

    public function testAnEmailRaceOnUpdateAppliesNothingAndIsCounted(): void
    {
        $user = $this->user('bob', 'bob@example.com');
        $this->commitConcurrently('carol', 'carol@example.com');

        $this->assertError(
            fn () => $this->service()->editSkippingTakenChecks(
                $user->id,
                self::$password,
                'bobby',
                'CAROL@example.com',
                null,
            ),
            409,
            'EMAIL_TAKEN',
            'Email already in use',
        );

        $this->assertUnchanged($user);
        $this->assertLockout($user->id, 1, null);
    }
}
