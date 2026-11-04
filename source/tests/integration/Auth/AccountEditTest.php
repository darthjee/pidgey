<?php

declare(strict_types=1);

namespace Pidgey\Tests\Integration\Auth;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Pidgey\Auth\AccountEdit;
use Pidgey\Auth\AccountEditOutcome;
use Pidgey\Auth\AccountEditResult;
use Pidgey\Auth\PasswordVerifier;
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
use Pidgey\Infra\WallClock;
use Pidgey\Model\AccountEditLockout;
use Pidgey\Model\Row;
use Pidgey\Model\User;
use Pidgey\Model\UserStatus;
use Pidgey\Repository\AccountEditLockoutRepository;
use Pidgey\Repository\PasswordResetTokenRepository;
use Pidgey\Repository\Sql;
use Pidgey\Repository\UserRepository;
use Pidgey\Tests\Support\Fixtures;
use Pidgey\Tests\Support\SessionEndpointTestCase;
use SensitiveParameter;

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
final class AccountEditTest extends SessionEndpointTestCase
{
    private const MAX_ATTEMPTS = 3;

    private const LOCK_TTL = 300;

    private const NOW = '2026-03-01 10:00:00';

    private const LOCK_END = '2026-03-01 10:05:00';

    /** A password generated once for the whole class, so no password literal is hardcoded. */
    private static string $password;

    /** A password that never matches, generated once for the whole class. */
    private static string $wrongPassword;

    /** A cheap bcrypt digest of the password (cost 4: password_needs_rehash() is true for it). */
    private static string $digest;

    /** Counts the password checks the service runs. */
    private int $verifications = 0;

    /**
     * Runs during the password check, i.e. inside the attempt's transaction once its snapshot
     * is set (by `findById()`) and before the "taken" checks and the update.
     *
     * @var (Closure(): void)|null
     */
    private ?Closure $duringVerification = null;

    private PasswordVerifier $verifier;

    private WallClock $clock;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$password = self::randomPassword('pw-');
        self::$wrongPassword = self::randomPassword('wrong-');
        self::$digest = password_hash(self::$password, PASSWORD_BCRYPT, ['cost' => 4]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->verifier = new class (function (): void {
            $this->verifications++;

            if ($this->duringVerification !== null) {
                ($this->duringVerification)();
            }
        }) implements PasswordVerifier {
            /** @param Closure(): void $onVerify */
            public function __construct(private readonly Closure $onVerify)
            {
            }

            public function verify(#[SensitiveParameter] string $password, #[SensitiveParameter] ?string $digest): bool
            {
                ($this->onVerify)();

                return (new Passwords())->verify($password, $digest);
            }
        };
        $this->clock = new class (self::NOW) implements WallClock {
            public function __construct(private readonly string $now)
            {
            }

            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable($this->now, new DateTimeZone('UTC'));
            }
        };
    }

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

    private function service(): AccountEdit
    {
        return new AccountEdit(
            new Transaction($this->pdo),
            new AccountEditLockoutRepository($this->pdo),
            new UserRepository($this->pdo),
            new PasswordResetTokenRepository($this->pdo),
            $this->verifier,
            new Passwords(),
            $this->clock,
            self::MAX_ATTEMPTS,
            self::LOCK_TTL,
        );
    }

    /**
     * Has a second connection commit a user with these values during the attempt's password
     * check: after the attempt's snapshot is set, before its "taken" checks and its update.
     */
    private function commitConcurrently(string $username, string $email): void
    {
        $this->duringVerification = function () use ($username, $email): void {
            $this->duringVerification = null;
            $other = self::pdo();
            (new Fixtures($other))->user(username: $username, email: $email);
            $this->assertFalse($other->inTransaction());
            $this->assertTrue($this->pdo->inTransaction());
            $snapshot = $this->pdo->prepare('SELECT 1 FROM users WHERE username = :username');
            $snapshot->execute(['username' => $username]);
            $this->assertFalse(
                $snapshot->fetchColumn(),
                'The attempt\'s snapshot must not see the concurrently committed user.',
            );
        };
    }

    private function user(string $username, string $email): User
    {
        $id = $this->fixtures->user(username: $username, email: $email, passwordDigest: self::$digest);

        return (new UserRepository($this->pdo))->findById($id) ?? self::fail('The fixture user is missing.');
    }

    private function reload(User $user): User
    {
        return (new UserRepository($this->pdo))->findById($user->id) ?? self::fail('The user is missing.');
    }

    private function wrongPassword(User $user): void
    {
        try {
            $this->service()->edit($user->id, self::$wrongPassword, 'bobby', null, null);
            $this->fail('Expected a BadRequestException');
        } catch (BadRequestException) {
            // Counted, as expected.
        }
    }

    private function assertUnchanged(User $user): void
    {
        $stored = $this->reload($user);
        $this->assertSame($user->username, $stored->username);
        $this->assertSame($user->email, $stored->email);
        $this->assertSame($user->passwordDigest, $stored->passwordDigest);
    }

    private function assertLockout(int $userId, int $failedAttempts, ?string $lockedUntil): void
    {
        $row = $this->lockoutRow($userId) ?? self::fail('The lockout row is missing.');
        $this->assertSame((string) $failedAttempts, (string) $row['failed_attempts']);
        $this->assertSame($lockedUntil, $row['locked_until']);
    }

    /**
     * @param callable(): mixed $call
     */
    private function assertError(callable $call, int $status, ?string $code, string $message): void
    {
        try {
            $call();
            $this->fail('Expected an HttpException');
        } catch (HttpException $error) {
            $this->assertSame($status, $error->status());
            $this->assertSame($code, $error->errorCode());
            $this->assertSame($message, $error->errorMessage());
        }
    }
}
