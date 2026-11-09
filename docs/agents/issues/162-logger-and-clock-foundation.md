# Issue: Logger and clock foundation

## Description

Part of epic #149 (email integration). Adds the logger and clock wiring that the SMTP
connection endpoints, the SMTP transport and the test-email rate limit all need.

What already exists:

- `Pidgey\Infra\Clock` (monotonic: `now(): float`, `sleep()`) with `SystemClock`, used by
  `DatabaseServer`.
- `Pidgey\Infra\WallClock` (UTC `now(): DateTimeImmutable`) with `SystemWallClock`, served by
  `Container::wallClock()`.
- `monolog/monolog ^3.9` in `require` and `composer.lock`.

There is no logger factory, no container logger, no `LOG_LEVEL` check and no shared frozen
clock.

### Specs

- `docs/agents/specs/architecture/backend.md#logging` (`LoggerFactory`);
- `docs/agents/specs/architecture/configuration.md#logging` (`LOG_LEVEL`);
- `docs/agents/specs/email/send-flow.md#timeouts` (clock use by the deadline);
- `docs/agents/specs/testing/backend.md#email-test-support` (`CapturedLog`, frozen clock);
- implementation notes: `docs/agents/specs/implement-email/endpoints.md` (logger),
  `implement-email/transport.md` (clock), `implement-email/testing.md` (`CapturedLog`).

## Problem

The SMTP connection endpoints, the SMTP transport and the test-email rate limit need an
application logger (with tests able to capture its records for the canary-password rule)
and clocks they can take from the container and freeze in tests. Today there is no
logger, no `LOG_LEVEL` check, no `Container::clock()`, and each test hand-builds its own
fixed clock; the specs also name `Clock` where wall time (`WallClock`) is meant.

## Expected Behavior

### Scope

#### Clock

- Both clocks are kept, with no merge and no new interface:
  - `Clock` (monotonic) is for the SMTP session deadline (`Pidgey\Smtp\Deadline`);
  - `WallClock` (UTC) is for the `Date` header and the test-email limit window, whose
    timestamps are stored in the database.
- Spec wording is fixed wherever `Clock` is named but `WallClock` is meant:
  `implement-email/transport.md` (the `Date` header), `implement-email/endpoints.md` (the
  limit), `testing/backend.md` (the frozen clock).
- `Container::clock(): Clock` is a memoized `SystemClock`, injectable the same way as
  `wallClock`, so the transport and the send flow get it from the container.
- Shared test doubles in `tests/support/`:
  - `FrozenClock` implements `Clock`: `now()` is fixed, and `sleep()` moves it forward
    instead of waiting.
  - `FrozenWallClock` implements `WallClock`: a fixed `DateTimeImmutable` plus
    `advance(seconds)`.

#### Logger

- `Pidgey\Infra\LoggerFactory` builds the application logger: Monolog `Logger`,
  `ErrorLogHandler`, minimum level from `LOG_LEVEL` (default `info`).
- `Container::logger(): LoggerInterface` is memoized and injectable.
- `ConfigValidator` / `bin/check-config` validate `LOG_LEVEL`; a bad value reports
  `LOG_LEVEL: invalid level` and is never echoed.
- `Pidgey\Tests\Support\CapturedLog` gives tests a container whose logger carries a Monolog
  `TestHandler`, with helpers to read the records.

#### Out of scope

- The log lines themselves (each feature issue adds its own).
- The Monolog dependency (already present).
- Migrating existing tests' ad-hoc `fixedClock(...)` helpers onto the shared doubles. New
  tests use the doubles; old tests move only when touched for other reasons.
- Moving `ErrorFormatter` and `StaticFileHandler` from their `error_log()` callable onto
  Monolog. That is #171, because the boot-refusal path builds an `ErrorFormatter` before
  `LOG_LEVEL` is validated.

### Edge cases

- **`LOG_LEVEL` unset or empty:** falls back to `info` and passes, following the existing
  `ConfigValidator` rule for optional settings.
- **`LOG_LEVEL` not an exact lower-case PSR-3 name** (`INFO`, ` info`, `warn`): reports
  `LOG_LEVEL: invalid level`. The validator is deliberately stricter than Monolog, which is
  case-insensitive.
- **`LoggerFactory` given an invalid level anyway** (e.g. a `bin/` script that skips
  validation): throws an `InvalidArgumentException` naming `LOG_LEVEL`, never the value. It
  never silently falls back.
- **Line format:**
  - Channel `pidgey`, keeping `ErrorLogHandler`'s default format:
    `pidgey.INFO: <message> <context JSON> []`.
  - No timestamp, since `error_log` adds its own.
  - Line breaks are flattened to spaces (the Monolog default), so a logged value cannot
    forge an extra log line.
- **Lazy logger:** `Container::logger()` builds the logger on first use, so a request that
  never logs never builds it.
- **Frozen clocks:**
  - `FrozenClock::sleep(0)` is a no-op; a negative value throws an
    `InvalidArgumentException`, matching `usleep`, which throws a `ValueError` in PHP 8.
  - `FrozenWallClock` is built from a Unix timestamp or a `DateTimeImmutable` and always
    returns UTC. `advance()` rejects negative seconds, so it never moves backwards.

### Backward compatibility

- **`Container` constructor: append, never reorder.** It becomes
  `(ConnectionFactory $connectionFactory = new PdoFactory(), ?WallClock $wallClock = null, ?Clock $clock = null, ?LoggerInterface $logger = null)`.
  - The ~50 existing positional calls (`new Container()`, `new Container($connections)`,
    `new Container($connections, $clock)`) stay unchanged.
  - New code passes the new arguments by name, e.g.
    `new Container(logger: $captured->logger())`.
- **Defaults:** `clock` falls back to `SystemClock` and `logger` to `LoggerFactory` (from
  `LOG_LEVEL`), both built on first use.
- **No behavior change for existing deployments:**
  - An unset `LOG_LEVEL` passes `ConfigValidator` / `bin/check-config`, so no host needs a
    new variable before release.
  - Nothing logs through Monolog yet (the feature issues and #171 add that), so the
    existing `error_log()` lines are unchanged.
- **Docs:** `deployment/server.md` and `deployment/variables.md` already describe
  `LOG_LEVEL`. The specs that describe the container gain `clock()` and `logger()`.

### Performance & security

#### Security

- **No processors:** the factory adds no Monolog processors (no `WebProcessor`,
  `IntrospectionProcessor`, `MemoryUsageProcessor`). A line holds only the channel, the
  level, and the caller's message and context, so no IP, URL, header or cookie leaks in.
- **No stack traces:** `includeStacktraces` stays off (the Monolog default). Traces can
  carry argument values, and the canary rule must not depend on the host's
  `zend.exception_ignore_args`.
- **Object normalization:** Monolog renders a context object as JSON (`JsonSerializable`),
  as `__toString()`, or else as its class name. This issue only documents the rule in the
  logging spec. The transport issue's `SecretString` must implement neither interface, so a
  misplaced secret logs as its class name.
- **Log forging:** line breaks are flattened (see Edge cases).
- **Enforcement:** `CapturedLog::assertNotLogged` is the canary-rule tool; the feature
  issues apply it on every path.

#### Performance

- A call below `LOG_LEVEL` is cheap, because Monolog checks the level before formatting.
  The logger is built on first use.
- `ErrorLogHandler` writes synchronously, one `error_log()` call per line. There is no
  buffering: a `BufferHandler` would lose lines on a fatal error, and the volume is low
  (operator actions, SMTP failures).
- Lines go wherever PHP's `error_log` points today (the host's Apache error log, per
  `deployment/server.md`), under mod_php or PHP-FPM. No new file, permission or rotation is
  needed.

## Solution

### Testing strategy

#### `CapturedLog`: inject, don't push

- `LoggerFactory::create(string $level, ?HandlerInterface $handler = null)` defaults the
  handler to `ErrorLogHandler`; `LoggerFactory::fromEnv()` reads `LOG_LEVEL` and calls
  `create()`.
- `CapturedLog` builds its logger through the same factory with a `TestHandler`, always at
  `debug`, so the canary checks see every line whatever `LOG_LEVEL` says.
- Tests inject it with `new Container(logger: $log->logger())`, or through the existing
  container-factory closures (`FrontController`, `SessionEndpointTestCase`).
- Rejected: pushing a handler onto the container's real logger.
  - `LoggerInterface` has no `pushHandler`, so it would need a downcast.
  - The real `ErrorLogHandler` would still write to the test output.
  - Building through the factory keeps the production channel and format.
- API:
  - `logger()`, `records()`;
  - `lines()`: each record formatted with the production line formatter;
  - `assertNotLogged(string $secret)`: fails if the secret appears in any line, plain or
    base64-encoded (the canary-password rule).

#### Tests

- **`LoggerFactoryTest`** (unit):
  - each of the 8 levels is accepted;
  - `info` drops `debug`;
  - the channel is `pidgey`;
  - an invalid level throws, naming `LOG_LEVEL` and not the value;
  - line breaks are flattened.

  The checks go through the handler's formatter, so no test writes to the real `error_log`.
- **`ConfigValidatorTest`:**
  - an unset or empty `LOG_LEVEL` passes;
  - each of the 8 levels passes;
  - `INFO`, ` info` and `warn` report `LOG_LEVEL: invalid level`;
  - the value never appears in the output.
- **`ContainerTest`:**
  - `clock()` defaults to `SystemClock`, is memoized and is injectable;
  - `logger()` is memoized, injectable, and built from `LOG_LEVEL` by default.
- **Support-class tests** under `tests/unit/Support/` (following
  `tests/integration/Support/MailpitTest.php`):
  - `FrozenClockTest`: `sleep` moves `now()` forward, `0` is a no-op, a negative value
    throws;
  - `FrozenWallClockTest`: UTC, `advance` moves forward, a negative value throws;
  - `CapturedLogTest`: records are captured, and `assertNotLogged` catches the plain and
    the base64 form.
- Acceptance runs through `make tests` (lint and both suites).

### Acceptance

- The container returns one logger honoring `LOG_LEVEL`; tests can capture its records.
- The container serves both clocks (`clock()` and `wallClock()`), and tests can freeze
  either one with the shared doubles.
- The specs name `Clock` only for monotonic use and `WallClock` for wall time.
- The matching items in the `implement-email/` detail files are ticked in the same PR.

### Dependencies

None.

Agents:

- **backend:** `LoggerFactory`, the `Container` accessors, `ConfigValidator`, and the test
  support classes with their tests.
- **product-owner:** the spec edits:
  - `Clock` → `WallClock` wherever wall time is meant (`implement-email/transport.md`,
    `implement-email/endpoints.md`, `testing/backend.md`);
  - `FrozenClock` / `FrozenWallClock` added to the `testing/backend.md` support-class table;
  - the object-normalization rule and the `create()` / `fromEnv()` factory shape in
    `architecture/backend.md#logging`;
  - the `implement-email/` checklist ticks.

## Benefits

- The email feature issues get one logger, two freezable clocks and a canary-check tool
  from the container, instead of each inventing its own.
- No existing behavior, constructor call or deployment variable changes.
