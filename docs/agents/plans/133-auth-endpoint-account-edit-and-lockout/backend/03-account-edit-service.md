# 03 — AccountEdit service and container wiring

`Pidgey\Auth\AccountEdit` (final) with constructor: `Transaction`, `AccountEditLockoutRepository`, `UserRepository`, `PasswordResetTokenRepository`, `PasswordVerifier`, `Passwords` (hashing), `WallClock`, `int $maxAttempts`, `int $lockTtl`.

`edit(int $userId, #[SensitiveParameter] string $currentPassword, ?string $username, ?string $email, #[SensitiveParameter] ?string $newPassword): AccountEditResult` (`username`, `email` after the update). Precondition (asserted by the handler): at least one change field is non-null.

Inside **one** `Transaction::run()` callback:
1. `ensure($userId)`, then `lockForUpdate($userId)`; if `lockedUntil !== null && lockedUntil <= now` → `clear()`; else if `lockedUntil > now` → return a `Locked` outcome.
2. `findById($userId)`; missing → return an `InvalidSession` outcome.
3. `verify()` fails → `recordFailure()` and return `WrongPassword`; a new username `!==` current and `usernameTakenByOther` → `recordFailure()`, `UsernameTaken`; same for email → `EmailTaken`.
4. Hash `newPassword` when present; `updateAccount(...)`; when email changed or password present → `markAllUnusedUsed($userId, now)`; `clear()`; return `Success`.

The callback **returns** outcomes instead of throwing, so the increment commits; after `run()` returns, map outcomes to exceptions: `LockedException('Account temporarily locked due to too many failed attempts')`, `UnauthorizedException('Invalid session')`, `BadRequestException('Invalid current password')`, `ConflictException('Username already in use', 'USERNAME_TAKEN')`, `ConflictException('Email already in use', 'EMAIL_TAKEN')`. Check the exact message/code constructors of `BadRequestException` / `UnauthorizedException`.

**Update race:** a `PDOException` with SQLSTATE `23000` from `updateAccount` rolls the transaction back (no change applied). Then, in a fresh transaction (ensure + lock + `recordFailure`), count the failure, and answer the matching `409` — determine which field by re-running the taken checks; if neither is taken, rethrow a generic `PDOException` carrying only the SQLSTATE (never chain the driver message). Expose the apply step (e.g. a public method or a flag skipping the taken checks) so the race is testable, as `Registration::create()` does.

**Container:** `accountEdit()` getter (memoized), reading `Env::integer('ACCOUNT_EDIT_MAX_ATTEMPTS', ConfigValidator::DEFAULT_ACCOUNT_EDIT_MAX_ATTEMPTS)` and `Env::integer('ACCOUNT_EDIT_LOCK_TTL', ConfigValidator::DEFAULT_ACCOUNT_EDIT_LOCK_TTL)`; add those two default constants (`5`, `300`) to `ConfigValidator`, and a `passwordResetTokenRepository()`-based wiring. Container unit/integration tests as for the other getters.

**Tests** (`source/tests/integration/Auth/AccountEditTest.php`, with a frozen `WallClock`):
- each field alone and all together; result values; digest changed only with `newPassword`; no rehash of the old digest;
- wrong password `400` and counter +1 committed (re-read after the exception);
- lock at exactly max failures; a counter already above max locks on the next failure; `423` while locked with the counter unchanged; an expired lock cleared, so one failure after expiry leaves `failed_attempts = 1` and no lock;
- `USERNAME_TAKEN` / `EMAIL_TAKEN` counted; case-only username change allowed; identical values skip the check;
- success resets the row;
- reset tokens marked used on email change and on password change, untouched on username-only change;
- update race (unique violation) → no change applied, `409`, counted;
- caller row missing → `401 Invalid session`, not counted;
- **concurrency:** (a) a second PDO connection (`ContainerTest`/`DatabaseCleanerTest` patterns; short `innodb_lock_wait_timeout`) holds the row `FOR UPDATE`; an `edit()` on the first connection fails with a lock-wait timeout **before** the counting verifier is called (0 verify calls); (b) N = max + 3 wrong-password attempts → exactly `max` verify calls, the remaining answer `423`.

## Files to Change
- `source/lib/Auth/AccountEdit.php`, `source/lib/Auth/AccountEditResult.php` (and a small outcome enum if useful) — new.
- `source/lib/Container.php` — `accountEdit()`.
- `source/lib/Infra/ConfigValidator.php` — default constants.
- `source/tests/integration/Auth/AccountEditTest.php`, container tests.
