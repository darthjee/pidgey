# Plan: Logger and clock foundation

Issue: [162-logger-and-clock-foundation.md](../../issues/162-logger-and-clock-foundation.md)

## Overview

Wire the application logger and the two clocks into the container, so the email feature
issues (#163 onwards) can take them from it and freeze or capture them in tests.

- **Backend** adds `Pidgey\Infra\LoggerFactory`, the `LOG_LEVEL` check, `Container::clock()`
  and `Container::logger()`, and the `FrozenClock`, `FrozenWallClock` and `CapturedLog` test
  support classes.
- **Product owner** fixes the specs: `Clock` for monotonic time, `WallClock` for wall time,
  the logger factory's shape, and the support-class table.

No behavior changes for existing deployments: `LOG_LEVEL` is optional, and nothing logs
through Monolog yet.

## Agents involved

- [backend](backend.md)
- [product-owner](product-owner.md)

## Shared contracts

### `Pidgey\Infra\LoggerFactory`

- `public const CHANNEL = 'pidgey';`
- `public const LEVELS = ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'];`
  This is the single source of the valid `LOG_LEVEL` values, reused by `ConfigValidator`.
- `public const DEFAULT_LEVEL = 'info';`
- `public const FORMAT = "%channel%.%level_name%: %message% %context% %extra%";`
  This is Monolog's `ErrorLogHandler` default format.
- `public static function create(string $level, ?HandlerInterface $handler = null): LoggerInterface`:
  - `$level` must be in `LEVELS` (exact, lower case). Otherwise it throws an
    `InvalidArgumentException` with the message `LOG_LEVEL: invalid level`, never the value.
  - The handler defaults to `new ErrorLogHandler()`.
  - The handler gets the minimum level and a `LineFormatter(FORMAT)` with line breaks
    flattened and stack traces off.
  - No processors.
- `public static function fromEnv(): LoggerInterface` calls
  `create(Env::get('LOG_LEVEL', DEFAULT_LEVEL))`.

### `Pidgey\Container`

- Constructor, appended and never reordered:
  `(ConnectionFactory $connectionFactory = new PdoFactory(), ?WallClock $wallClock = null, ?Clock $clock = null, ?LoggerInterface $logger = null)`.
- `clock(): Clock`: memoized, defaults to `new SystemClock()`.
- `logger(): LoggerInterface`: memoized, defaults to `LoggerFactory::fromEnv()`.

### `ConfigValidator`

- An unset or empty `LOG_LEVEL` passes.
- Any value not in `LoggerFactory::LEVELS` adds the failure line `LOG_LEVEL: invalid level`.

### Test support (`Pidgey\Tests\Support\`, `source/tests/support/`)

- **`FrozenClock implements Clock`:**
  - `__construct(float $now = 0.0)`;
  - `sleep(float $seconds)` adds the seconds to `now()`: `0` is a no-op, and a negative
    value throws an `InvalidArgumentException`.
- **`FrozenWallClock implements WallClock`:**
  - `__construct(int|DateTimeImmutable $at)`;
  - `now()` returns UTC;
  - `advance(int $seconds)` moves forward, and a negative value throws an
    `InvalidArgumentException`.
- **`CapturedLog`:**
  - `__construct()` builds `LoggerFactory::create('debug', new TestHandler())`;
  - `logger(): LoggerInterface`;
  - `records(): list<LogRecord>`;
  - `lines(): list<string>`: the formatted lines;
  - `assertNotLogged(string $secret): void` fails if any line contains `$secret` or
    `base64_encode($secret)`.
