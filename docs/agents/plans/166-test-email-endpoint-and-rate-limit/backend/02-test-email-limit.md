# Test-email limit and 429

Implement the window and the lease. The proposed split follows the spec's names.

- `Pidgey\Repository\SmtpTestLimitRepository` (raw PDO, prepared statements). Every method
  takes `DateTimeImmutable` values from `WallClock`:
  - `lockForOrganization(int $organizationId, DateTimeImmutable $now)`: upserts the row
    (`INSERT … ON DUPLICATE KEY UPDATE organization_id = organization_id`, with
    `window_started_at = $now`, `attempts = 0`), then `SELECT … FOR UPDATE` and returns a
    small value (`windowStartedAt`, `attempts`, `busyUntil`). It must run inside a
    transaction. This is the same pattern as `AccountEditLockoutRepository`.
  - `takeAttempt(int $organizationId, DateTimeImmutable $windowStartedAt, int $attempts, DateTimeImmutable $busyUntil)`:
    writes the new window, count and lease.
  - `releaseLease(int $organizationId, DateTimeImmutable $busyUntil)`: a single autocommit
    `UPDATE … SET busy_until = NULL WHERE organization_id = ? AND busy_until = ?`.
- `Pidgey\Email\TestEmailLimit`, which applies the rules. It is built with the repository,
  `Transaction`, `WallClock`, max attempts, window and lease seconds:
  - `acquire(int $organizationId): TestEmailLease`. It reads `now` once and, in one short
    `Transaction::run`, locks the row. When the lease is held (`busy_until > now`), it throws
    `TooManyRequestsException('A test email is already in progress', retryAfter = busy_until - now)`.
    When `window_started_at + window <= now`, it starts a new window (`now`, `0`). When the
    count is reached, it throws `TooManyRequestsException('Too many test emails; try again later', retryAfter = window end - now)`.
    Otherwise it increments the count and sets `busy_until = now + lease`, commits, and
    returns a lease holding the `busy_until` it set.
  - `release(TestEmailLease $lease)` calls `releaseLease`. It catches any `Throwable` and
    logs `warning` with `organizationId` and `connectionId` and the exception class only, so
    a failed release never changes the response.
  - A refused attempt leaves the row unchanged (the transaction only reads it).
  - `Retry-After = max(1, ceil(seconds))`.
- `Pidgey\Http\TooManyRequestsException extends HttpException`: status `429`, code `null`
  (the formatter fills in `TOO_MANY_REQUESTS`), the message, and `retryAfter(): int`.
- Header support: add `headers(): array` (default `[]`) to `HttpException`, override it in
  `TooManyRequestsException` to return `['Retry-After: <n>']`, and make
  `ErrorFormatter::formatHttpException` add those header lines to the `ErrorResponse`
  (`Response::addHeader`). No other exception changes behavior.
- `Pidgey\Container`: `smtpTestLimitRepository()` and `testEmailLimit()`.
- Tests:
  - repository integration tests (`RepositoryTestCase`): upsert on the first call; no
    duplicate row on concurrent first calls (two connections, as in the existing
    concurrency tests); `takeAttempt`; compare-and-clear leaves a newer `busy_until` alone;
  - `TestEmailLimit` with `FrozenWallClock`:
    - the window boundary (`<=` starts a new window);
    - the count reached, with its `Retry-After`;
    - the lease checked before the window;
    - an expired lease from a dead worker is ignored;
    - a refused attempt does not count;
    - rounding (`0.2 s` → `1`);
    - a failing release still returns normally and logs the warning (`CapturedLog`);
  - `ErrorFormatter`: a `429` carries the `Retry-After` header and the envelope.

## Files to Change

- `source/lib/Repository/SmtpTestLimitRepository.php`: new.
- `source/lib/Email/TestEmailLimit.php` and `source/lib/Email/TestEmailLease.php`: new.
- `source/lib/Http/TooManyRequestsException.php`: new.
- `source/lib/Http/HttpException.php`: `headers()`.
- `source/lib/Http/ErrorFormatter.php`: apply the exception's headers.
- `source/lib/Container.php`: the wiring.
- `source/tests/integration/Repository/SmtpTestLimitRepositoryTest.php`: new.
- `source/tests/unit/Email/TestEmailLimitTest.php` (or integration, if it needs the DB): new.
- `source/tests/unit/Http/ErrorFormatterTest.php`: the header case.
