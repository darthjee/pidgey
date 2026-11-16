<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Auth;

use PDOException;
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
final class AccountEditLockoutTest extends AccountEditTestCase
{
    public function testWrongPasswordIsRefusedAndCounted(): void
    {
        $user = $this->user('bob', 'bob@example.com');

        $this->assertError(
            fn () => $this->service()->edit($user->id, self::$wrongPassword, 'bobby', null, null),
            400,
            null,
            'Invalid current password',
        );

        $this->assertSame('bob', $this->reload($user)->username);
        $this->assertLockout($user->id, 1, null);
    }

    public function testLocksAtExactlyTheMaximum(): void
    {
        $user = $this->user('bob', 'bob@example.com');
        $this->setLockout($user->id, self::MAX_ATTEMPTS - 2);

        $this->wrongPassword($user);
        $this->assertLockout($user->id, self::MAX_ATTEMPTS - 1, null);

        $this->wrongPassword($user);
        $this->assertLockout($user->id, self::MAX_ATTEMPTS, self::LOCK_END);
    }

    public function testACounterAboveTheMaximumStillLocks(): void
    {
        $user = $this->user('bob', 'bob@example.com');
        $this->setLockout($user->id, self::MAX_ATTEMPTS + 4);

        $this->wrongPassword($user);

        $this->assertLockout($user->id, self::MAX_ATTEMPTS + 5, self::LOCK_END);
    }

    public function testLockedAnswers423WithoutCheckingOrCounting(): void
    {
        $user = $this->user('bob', 'bob@example.com');
        $this->setLockout($user->id, self::MAX_ATTEMPTS, '2026-03-01 10:00:01');

        $this->assertError(
            fn () => $this->service()->edit($user->id, self::$password, 'bobby', null, null),
            423,
            null,
            'Account temporarily locked due to too many failed attempts',
        );

        $this->assertSame(0, $this->verifications);
        $this->assertSame('bob', $this->reload($user)->username);
        $this->assertLockout($user->id, self::MAX_ATTEMPTS, '2026-03-01 10:00:01');
    }

    public function testAnExpiredLockIsClearedBeforeTheAttempt(): void
    {
        $user = $this->user('bob', 'bob@example.com');
        $this->setLockout($user->id, self::MAX_ATTEMPTS, self::NOW);

        $this->wrongPassword($user);

        $this->assertLockout($user->id, 1, null);
    }

    public function testAnExpiredLockIsClearedBeforeASuccess(): void
    {
        $user = $this->user('bob', 'bob@example.com');
        $this->setLockout($user->id, self::MAX_ATTEMPTS + 2, '2026-03-01 09:59:59');

        $this->service()->edit($user->id, self::$password, 'bobby', null, null);

        $this->assertSame('bobby', $this->reload($user)->username);
        $this->assertLockout($user->id, 0, null);
    }

    public function testSuccessResetsTheLockoutRow(): void
    {
        $user = $this->user('bob', 'bob@example.com');
        $this->setLockout($user->id, self::MAX_ATTEMPTS - 1);

        $this->service()->edit($user->id, self::$password, 'bobby', null, null);

        $this->assertLockout($user->id, 0, null);
    }

    public function testAMissingCallerAnswersInvalidSessionWithoutCounting(): void
    {
        $missing = $this->user('bob', 'bob@example.com')->id + 1000;

        $this->assertError(
            fn () => $this->service()->edit($missing, self::$password, 'bobby', null, null),
            401,
            null,
            'Invalid session',
        );

        $this->assertSame(0, $this->verifications);
        $this->assertNull($this->lockoutRow($missing));
    }

    public function testAnAttemptWaitsForTheLockoutRowBeforeCheckingThePassword(): void
    {
        $user = $this->user('bob', 'bob@example.com');
        $this->setLockout($user->id, 0);
        $other = self::pdo();
        $other->beginTransaction();
        $other->prepare('SELECT id FROM account_edit_lockouts WHERE user_id = :user_id FOR UPDATE')
            ->execute(['user_id' => $user->id]);
        $this->pdo->exec('SET SESSION innodb_lock_wait_timeout = 1');

        try {
            $this->service()->edit($user->id, self::$wrongPassword, 'bobby', null, null);
            $this->fail('Expected a lock wait timeout');
        } catch (PDOException $exception) {
            $this->assertStringContainsString('1205', (string) $exception->errorInfo[1]);
        } finally {
            $other->rollBack();
        }

        $this->assertSame(0, $this->verifications);
        $this->assertFalse($this->pdo->inTransaction());
        $this->assertLockout($user->id, 0, null);
    }

    public function testRepeatedWrongPasswordsCheckAtMostTheMaximum(): void
    {
        $user = $this->user('bob', 'bob@example.com');
        $statuses = [];

        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS + 3; $attempt++) {
            try {
                $this->service()->edit($user->id, self::$wrongPassword, 'bobby', null, null);
            } catch (HttpException $error) {
                $statuses[] = $error->status();
            }
        }

        $this->assertSame(self::MAX_ATTEMPTS, $this->verifications);
        $this->assertSame([400, 400, 400, 423, 423, 423], $statuses);
        $this->assertLockout($user->id, self::MAX_ATTEMPTS, self::LOCK_END);
    }
}
