<?php

declare(strict_types=1);

namespace Pidgey\Auth;

use DateInterval;
use LogicException;
use PDOException;
use Pidgey\Http\BadRequestException;
use Pidgey\Http\ConflictException;
use Pidgey\Http\HttpException;
use Pidgey\Http\LockedException;
use Pidgey\Http\UnauthorizedException;
use Pidgey\Infra\Transaction;
use Pidgey\Infra\WallClock;
use Pidgey\Model\AccountEditLockout;
use Pidgey\Model\User;
use Pidgey\Repository\AccountEditLockoutRepository;
use Pidgey\Repository\PasswordResetTokenRepository;
use Pidgey\Repository\UserRepository;
use SensitiveParameter;

/**
 * Edits the caller's own username, email and/or password, with a failed-attempt lockout
 * (see `api/endpoints/auth/account.md`).
 *
 * Every attempt runs in one transaction holding the caller's lockout row `FOR UPDATE`, from the
 * lock check through the password verification and the "taken" checks to the increment or the
 * reset, so concurrent attempts of the same user are serialized. Failures are returned out of
 * the transaction as an AccountEditOutcome, so their increment is committed, and only then turned
 * into an HTTP error. Nothing here is logged; the digest is never rehashed and no session is
 * revoked.
 *
 * The "taken" checks are locking reads (`FOR SHARE`): under REPEATABLE READ the transaction's
 * snapshot is set by the first plain read (`findById()`), so a plain read would miss a username
 * or email committed by another transaction after it, while the update's unique check would not.
 */
final class AccountEdit
{
    public const LOCKED_MESSAGE = 'Account temporarily locked due to too many failed attempts';

    public const INVALID_SESSION_MESSAGE = 'Invalid session';

    public const INVALID_PASSWORD_MESSAGE = 'Invalid current password';

    public const USERNAME_TAKEN_MESSAGE = 'Username already in use';

    public const EMAIL_TAKEN_MESSAGE = 'Email already in use';

    /** SQLSTATE of an integrity constraint violation (e.g. a duplicate unique key). */
    private const INTEGRITY_VIOLATION = '23000';

    /** MySQL error number of a foreign key violation on insert (`ER_NO_REFERENCED_ROW_2`). */
    private const FOREIGN_KEY_VIOLATION = 1452;

    /**
     * Builds the service.
     *
     * @param Transaction                  $transaction Runs each attempt atomically.
     * @param AccountEditLockoutRepository $lockouts    The `account_edit_lockouts` repository.
     * @param UserRepository               $users       The `users` repository.
     * @param PasswordResetTokenRepository $resetTokens The `password_reset_tokens` repository.
     * @param PasswordVerifier             $verifier    Checks the current password.
     * @param Passwords                    $passwords   Hashes the new password.
     * @param WallClock                    $clock       Source of `now`.
     * @param integer                      $maxAttempts Failures before locking (`ACCOUNT_EDIT_MAX_ATTEMPTS`).
     * @param integer                      $lockTtl     Lock duration in seconds (`ACCOUNT_EDIT_LOCK_TTL`).
     */
    public function __construct(
        private readonly Transaction $transaction,
        private readonly AccountEditLockoutRepository $lockouts,
        private readonly UserRepository $users,
        private readonly PasswordResetTokenRepository $resetTokens,
        private readonly PasswordVerifier $verifier,
        private readonly Passwords $passwords,
        private readonly WallClock $clock,
        private readonly int $maxAttempts,
        private readonly int $lockTtl,
    ) {
    }

    /**
     * Checks the lockout, the current password and the new values, then applies the changes.
     *
     * The caller (the handler) has already rejected an edit with nothing to change.
     *
     * @param integer     $userId          The authenticated caller.
     * @param string      $currentPassword The current password, never trimmed.
     * @param string|null $username        The new username (trimmed), or null when not present.
     * @param string|null $email           The new email (trimmed), or null when not present.
     * @param string|null $newPassword     The new password (never trimmed), or null when not present.
     *
     * @return AccountEditResult The caller's values after the update.
     *
     * @throws HttpException  `423` locked, `401` invalid session, `400` wrong password, `409` taken.
     * @throws PDOException   When a statement fails for another reason.
     * @throws LogicException When a transaction is already active on the connection.
     */
    public function edit(
        int $userId,
        #[SensitiveParameter] string $currentPassword,
        ?string $username,
        ?string $email,
        #[SensitiveParameter] ?string $newPassword,
    ): AccountEditResult {
        return $this->attempt($userId, $currentPassword, $username, $email, $newPassword, true);
    }

    /**
     * Same as edit() without the "taken" pre-checks, so the unique-index race of the update (and
     * its re-check) can be tested (as `Registration::create()` does).
     *
     * @internal Test seam only; production callers use edit().
     *
     * @param integer     $userId          The authenticated caller.
     * @param string      $currentPassword The current password, never trimmed.
     * @param string|null $username        The new username (trimmed), or null when not present.
     * @param string|null $email           The new email (trimmed), or null when not present.
     * @param string|null $newPassword     The new password (never trimmed), or null when not present.
     *
     * @return AccountEditResult The caller's values after the update.
     *
     * @throws HttpException  `423` locked, `401` invalid session, `400` wrong password, `409` taken.
     * @throws PDOException   When a statement fails for another reason.
     * @throws LogicException When a transaction is already active on the connection.
     */
    public function editSkippingTakenChecks(
        int $userId,
        #[SensitiveParameter] string $currentPassword,
        ?string $username,
        ?string $email,
        #[SensitiveParameter] ?string $newPassword,
    ): AccountEditResult {
        return $this->attempt($userId, $currentPassword, $username, $email, $newPassword, false);
    }

    /**
     * Runs one attempt in a transaction, then answers its outcome.
     *
     * @param integer     $userId          The authenticated caller.
     * @param string      $currentPassword The current password.
     * @param string|null $username        The new username, or null.
     * @param string|null $email           The new email, or null.
     * @param string|null $newPassword     The new password, or null.
     * @param boolean     $checkTaken      Whether the "taken" pre-checks run.
     *
     * @return AccountEditResult
     *
     * @throws HttpException When the attempt fails.
     * @throws PDOException  When a statement fails for another reason.
     */
    private function attempt(
        int $userId,
        #[SensitiveParameter] string $currentPassword,
        ?string $username,
        ?string $email,
        #[SensitiveParameter] ?string $newPassword,
        bool $checkTaken,
    ): AccountEditResult {
        $outcome = $this->transaction->run(
            fn (): AccountEditResult|AccountEditOutcome => $this->evaluate(
                $userId,
                $currentPassword,
                $username,
                $email,
                $newPassword,
                $checkTaken,
            ),
        );

        if ($outcome instanceof AccountEditResult) {
            return $outcome;
        }

        throw self::error($outcome);
    }

    /**
     * The body of the transaction: steps 2 to 5 of the spec, in order.
     *
     * @param integer     $userId          The authenticated caller.
     * @param string      $currentPassword The current password.
     * @param string|null $username        The new username, or null.
     * @param string|null $email           The new email, or null.
     * @param string|null $newPassword     The new password, or null.
     * @param boolean     $checkTaken      Whether the "taken" pre-checks run.
     *
     * @return AccountEditResult|AccountEditOutcome
     *
     * @throws PDOException When a statement fails for another reason.
     */
    private function evaluate(
        int $userId,
        #[SensitiveParameter] string $currentPassword,
        ?string $username,
        ?string $email,
        #[SensitiveParameter] ?string $newPassword,
        bool $checkTaken,
    ): AccountEditResult|AccountEditOutcome {
        $lockout = $this->lockRow($userId);

        if ($lockout === null) {
            return AccountEditOutcome::InvalidSession;
        }

        if ($this->isLocked($lockout)) {
            return AccountEditOutcome::Locked;
        }

        $user = $this->users->findById($userId);

        if ($user === null) {
            return AccountEditOutcome::InvalidSession;
        }

        if (!$this->verifier->verify($currentPassword, $user->passwordDigest)) {
            return $this->fail($userId, AccountEditOutcome::WrongPassword);
        }

        $taken = $checkTaken ? $this->taken($user, $username, $email) : null;

        if ($taken !== null) {
            return $this->fail($userId, $taken);
        }

        return $this->apply($user, $username, $email, $newPassword);
    }

    /**
     * Makes sure the caller's lockout row exists (atomic upsert), then locks it `FOR UPDATE`.
     *
     * Only a foreign key violation of the upsert (MySQL error 1452: the caller's row is gone)
     * means a missing caller; any other failure is rethrown.
     *
     * @param integer $userId The authenticated caller.
     *
     * @return AccountEditLockout|null The locked row, or null when the caller's user row is missing.
     *
     * @throws PDOException When a statement fails for another reason.
     */
    private function lockRow(int $userId): ?AccountEditLockout
    {
        try {
            $this->lockouts->ensure($userId);
        } catch (PDOException $exception) {
            if ((int) ($exception->errorInfo[1] ?? 0) !== self::FOREIGN_KEY_VIOLATION) {
                throw $exception;
            }

            return null;
        }

        return $this->lockouts->lockForUpdate($userId);
    }

    /**
     * Clears an expired lock and tells whether the locked row is still locked.
     *
     * @param AccountEditLockout $lockout The caller's locked row.
     *
     * @return boolean
     *
     * @throws PDOException When a statement fails.
     */
    private function isLocked(AccountEditLockout $lockout): bool
    {
        $lockedUntil = $lockout->lockedUntil;

        if ($lockedUntil === null) {
            return false;
        }

        if ($lockedUntil > $this->clock->now()) {
            return true;
        }

        $this->lockouts->clear($lockout->userId);

        return false;
    }

    /**
     * Returns the first "taken" failure: a changed username, then a changed email, used by
     * another user (compared with the column collation, in the database).
     *
     * @param User        $user     The caller's current row.
     * @param string|null $username The new username, or null.
     * @param string|null $email    The new email, or null.
     *
     * @return AccountEditOutcome|null Null when neither is taken.
     *
     * @throws PDOException When a statement fails.
     */
    private function taken(User $user, ?string $username, ?string $email): ?AccountEditOutcome
    {
        if (self::changes($username, $user->username) && $this->users->usernameTakenByOther($username, $user->id)) {
            return AccountEditOutcome::UsernameTaken;
        }

        if (self::changes($email, $user->email) && $this->users->emailTakenByOther($email, $user->id)) {
            return AccountEditOutcome::EmailTaken;
        }

        return null;
    }

    /**
     * Applies the changes, invalidates the reset tokens when the email or password changes and
     * resets the lockout row.
     *
     * A unique-index violation on the update (a concurrent change took the value) applies
     * nothing, as InnoDB rolls back the failed statement, and is counted as a failure with the
     * matching `409`; the re-check is a locking read, so it sees the concurrently committed row
     * the update collided with. Any other integrity violation is rethrown as a generic `PDOException`
     * carrying only the SQLSTATE, as the driver message quotes user data.
     *
     * @param User        $user        The caller's current row.
     * @param string|null $username    The new username, or null.
     * @param string|null $email       The new email, or null.
     * @param string|null $newPassword The new password, or null.
     *
     * @return AccountEditResult|AccountEditOutcome
     *
     * @throws PDOException When a statement fails for another reason.
     */
    private function apply(
        User $user,
        ?string $username,
        ?string $email,
        #[SensitiveParameter] ?string $newPassword,
    ): AccountEditResult|AccountEditOutcome {
        $newUsername = $username ?? $user->username;
        $newEmail = $email ?? $user->email;
        $digest = $newPassword === null ? $user->passwordDigest : $this->passwords->hash($newPassword);

        try {
            $this->users->updateAccount($user->id, $newUsername, $newEmail, $digest);
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() !== self::INTEGRITY_VIOLATION) {
                throw $exception;
            }

            return $this->fail($user->id, $this->taken($user, $username, $email) ?? throw new PDOException(sprintf(
                'Integrity constraint violation (SQLSTATE %s) on account edit with no taken username or email',
                self::INTEGRITY_VIOLATION,
            )));
        }

        if ($newPassword !== null || self::changes($email, $user->email)) {
            $this->resetTokens->markAllUnusedUsed($user->id, $this->clock->now());
        }

        $this->lockouts->clear($user->id);

        return new AccountEditResult($newUsername, $newEmail);
    }

    /**
     * Counts one failed attempt (locking at the threshold) and returns the outcome.
     *
     * @param integer            $userId  The authenticated caller.
     * @param AccountEditOutcome $outcome The failure.
     *
     * @return AccountEditOutcome
     *
     * @throws PDOException When a statement fails.
     */
    private function fail(int $userId, AccountEditOutcome $outcome): AccountEditOutcome
    {
        $lockUntil = $this->clock->now()->add(new DateInterval('PT' . $this->lockTtl . 'S'));
        $this->lockouts->recordFailure($userId, $this->maxAttempts, $lockUntil);

        return $outcome;
    }

    /**
     * Tells whether a present value differs byte-wise from the current one.
     *
     * @param string|null $value   The new value, or null when not present.
     * @param string      $current The current value.
     *
     * @return boolean
     *
     * @phpstan-assert-if-true string $value
     */
    private static function changes(?string $value, string $current): bool
    {
        return $value !== null && $value !== $current;
    }

    /**
     * Maps a refused attempt to its HTTP error.
     *
     * @param AccountEditOutcome $outcome The failure.
     *
     * @return HttpException
     */
    private static function error(AccountEditOutcome $outcome): HttpException
    {
        return match ($outcome) {
            AccountEditOutcome::Locked => new LockedException(self::LOCKED_MESSAGE),
            AccountEditOutcome::InvalidSession => new UnauthorizedException(self::INVALID_SESSION_MESSAGE),
            AccountEditOutcome::WrongPassword => new BadRequestException(self::INVALID_PASSWORD_MESSAGE),
            AccountEditOutcome::UsernameTaken => new ConflictException(self::USERNAME_TAKEN_MESSAGE, 'USERNAME_TAKEN'),
            AccountEditOutcome::EmailTaken => new ConflictException(self::EMAIL_TAKEN_MESSAGE, 'EMAIL_TAKEN'),
        };
    }
}
