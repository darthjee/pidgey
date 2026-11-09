# Test support: frozen clocks and CapturedLog

Add three classes in `source/tests/support/` (namespace `Pidgey\Tests\Support\`), exactly as
in the [shared contracts](../plan.md#shared-contracts).

- **`FrozenClock implements Clock`:**
  - holds a float `now`;
  - `sleep(0)` is a no-op;
  - a negative value throws an `InvalidArgumentException`. This mirrors `usleep`, which
    throws a `ValueError` on a negative value.
- **`FrozenWallClock implements WallClock`:**
  - takes a Unix timestamp or a `DateTimeImmutable`, and stores it converted to UTC;
  - `now()` returns it;
  - `advance(int $seconds)` moves forward, and a negative value throws an
    `InvalidArgumentException`.
- **`CapturedLog`:**
  - built through `LoggerFactory::create('debug', $testHandler)`, so the channel and format
    match production;
  - exposes `logger()`, `records()` and `lines()`. `lines()` returns each record's
    `formatted` string, which `TestHandler` fills with the factory's formatter;
  - `assertNotLogged(string $secret)` uses PHPUnit's `Assert` to fail with a message that
    never contains the secret itself, e.g. "secret found in log line N (plain|base64)";
  - its docblock says how tests inject it: `new Container(logger: $log->logger())`, or
    through the container-factory closures of `FrontController` and
    `SessionEndpointTestCase`.

**Tests (`source/tests/unit/Support/`):**

- **`FrozenClockTest`:** `now()` starts at the given value, `sleep(1.5)` adds `1.5`,
  `sleep(0)` changes nothing, and `sleep(-1)` throws.
- **`FrozenWallClockTest`:** both constructor forms work, `now()` is in UTC even from a
  non-UTC `DateTimeImmutable`, `advance(60)` adds 60 seconds, and `advance(-1)` throws.
- **`CapturedLogTest`:**
  - a `debug` record is captured, and its line starts with `pidgey.DEBUG:`;
  - `assertNotLogged` passes when the secret is absent;
  - it fails (catch `AssertionFailedError`) when the secret appears in plain form in the
    message, in plain form in the context, or base64-encoded;
  - the failure message does not contain the secret.

These tests also keep the phpmd unused-code gate satisfied until the feature issues use the
classes.

## Files to Change

- `source/tests/support/FrozenClock.php`: new.
- `source/tests/support/FrozenWallClock.php`: new.
- `source/tests/support/CapturedLog.php`: new.
- `source/tests/unit/Support/FrozenClockTest.php`: new.
- `source/tests/unit/Support/FrozenWallClockTest.php`: new.
- `source/tests/unit/Support/CapturedLogTest.php`: new.
