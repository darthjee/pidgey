<?php

declare(strict_types=1);

namespace Pidgey\Tests\Support;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use Pidgey\Auth\AccountEdit;
use Pidgey\Auth\PasswordVerifier;
use Pidgey\Auth\Passwords;
use Pidgey\Http\BadRequestException;
use Pidgey\Infra\Transaction;
use Pidgey\Infra\WallClock;
use Pidgey\Model\User;
use Pidgey\Repository\AccountEditLockoutRepository;
use Pidgey\Repository\PasswordResetTokenRepository;
use Pidgey\Repository\UserRepository;

/**
 * Base class for the account edit service tests.
 */
abstract class AccountEditTestCase extends SessionEndpointTestCase
{
    use AccountEditAssertions;

    protected const MAX_ATTEMPTS = 3;

    protected const LOCK_TTL = 300;

    protected const NOW = '2026-03-01 10:00:00';

    protected const LOCK_END = '2026-03-01 10:05:00';

    /** A password generated once for the whole class, so no password literal is hardcoded. */
    protected static string $password;

    /** A password that never matches, generated once for the whole class. */
    protected static string $wrongPassword;

    /** A cheap bcrypt digest of the password (cost 4: password_needs_rehash() is true for it). */
    protected static string $digest;

    /** Counts the password checks the service runs. */
    protected int $verifications = 0;

    /**
     * Runs during the password check, i.e. inside the attempt's transaction once its snapshot
     * is set (by `findById()`) and before the "taken" checks and the update.
     *
     * @var (Closure(): void)|null
     */
    protected ?Closure $duringVerification = null;

    protected PasswordVerifier $verifier;

    protected WallClock $clock;

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

        $this->verifier = new CountingPasswordVerifier(function (): void {
            $this->verifications++;

            if ($this->duringVerification !== null) {
                ($this->duringVerification)();
            }
        });
        $this->clock = new FrozenWallClock(new DateTimeImmutable(self::NOW, new DateTimeZone('UTC')));
    }

    protected function service(): AccountEdit
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
    protected function commitConcurrently(string $username, string $email): void
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

    protected function user(string $username, string $email): User
    {
        $id = $this->fixtures->user(username: $username, email: $email, passwordDigest: self::$digest);

        return (new UserRepository($this->pdo))->findById($id) ?? self::fail('The fixture user is missing.');
    }

    protected function reload(User $user): User
    {
        return (new UserRepository($this->pdo))->findById($user->id) ?? self::fail('The user is missing.');
    }

    protected function wrongPassword(User $user): void
    {
        try {
            $this->service()->edit($user->id, self::$wrongPassword, 'bobby', null, null);
            $this->fail('Expected a BadRequestException');
        } catch (BadRequestException) {
            // Counted, as expected.
        }
    }
}
