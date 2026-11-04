# 02 — Repository additions

Pure data access; no lockout rule in the repositories (thresholds and times are decided by the service). Every `now` / timestamp is a bound parameter.

**`AccountEditLockoutRepository`**
- `ensure(int $userId): void` — atomic upsert creating the row if missing and leaving an existing row unchanged (`INSERT … ON DUPLICATE KEY UPDATE user_id = user_id`).
- `lockForUpdate(int $userId): AccountEditLockout` — `SELECT … FOR UPDATE` (must run inside a transaction).
- `clear(int $userId): void` — `failed_attempts = 0, locked_until = NULL` (used for an expired lock and for the success reset).
- `recordFailure(int $userId, int $maxAttempts, DateTimeImmutable $lockUntil): AccountEditLockout` — `INSERT … ON DUPLICATE KEY UPDATE failed_attempts = failed_attempts + 1`, then set `locked_until = :lockUntil` when the **incremented** value is `>= :max` (one statement with `IF(failed_attempts + 1 >= …)` ordering care, or re-read under the row lock then update); returns the row re-read.

**`UserRepository`**
- `usernameTakenByOther(string $username, int $userId): bool` and `emailTakenByOther(string $email, int $userId): bool` — `WHERE … = :value AND id <> :id`, column collation.
- `updateAccount(int $id, string $username, string $email, #[SensitiveParameter] string $digest): void` — single `UPDATE` (the service passes current values for unchanged fields).

**`PasswordResetTokenRepository`**
- `markAllUnusedUsed(int $userId, DateTimeImmutable $now): int` — `UPDATE … SET used_at = :now WHERE user_id = :user_id AND used_at IS NULL`; returns the affected count.

Integration tests in the matching `*RepositoryTest` classes: upsert idempotence (two `ensure` calls → one row, counter unchanged); increments below / at / above the threshold (`>=`, a counter already above max still locks); `clear`; "taken by other" excludes the caller and matches case-insensitively; `updateAccount`; `markAllUnusedUsed` touches only that user's unused tokens.

## Files to Change
- `source/lib/Repository/AccountEditLockoutRepository.php`
- `source/lib/Repository/UserRepository.php`
- `source/lib/Repository/PasswordResetTokenRepository.php`
- `source/tests/integration/Repository/AccountEditLockoutRepositoryTest.php`, `UserRepositoryTest.php`, `PasswordResetTokenRepositoryTest.php`
