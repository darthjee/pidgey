# Concurrency tests and lock holder

- `source/tests/fixtures/scripts/organization-lock-holder.php` per the shared contract (loads
  the test env like the other child scripts, own PDO, prints `LOCKED`, sleeps the hold time,
  optionally activates a connection, commits, exits `0`).
- Integration tests (real MySQL, real waits, loose bounds, child reaped in teardown with its
  exit status and empty stderr asserted):
  - child holds the lock and activates connection A; the parent's `activate` of B blocks
    until the child commits, then wins → exactly one active (B);
  - child holds the lock and activates A; parent `deactivate` of A after it → none active;
  - `PATCH` racing `activate` through `LockHookPdo::beforeNextLock` (activate another
    connection between the read and the row lock): the activation stands, `PATCH` never
    writes `active`;
  - two `PATCH` calls on the same row serialized by the row lock (the second validates the
    state left by the first).

## Files to Change
- `source/tests/fixtures/scripts/organization-lock-holder.php` — new.
- `source/tests/integration/Http/Handler/SmtpConnectionConcurrencyTest.php` — new.
