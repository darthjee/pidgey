# Issue: Move ErrorFormatter and StaticFileHandler onto the Monolog logger

## Description

Follow-up of #162 (logger and clock foundation). `Pidgey\Http\ErrorFormatter` and
`Pidgey\Http\Handler\StaticFileHandler` still log through an `error_log()` callable
(`ErrorFormatter`'s docblock: "Monolog replaces it once logging is wired"). Now that #162
added `Pidgey\Infra\LoggerFactory` and `Container::logger()`, move both onto the
application's Monolog logger, built once at boot as `request-handler/front-controller.md`
already specifies ("Logger (Monolog), first, so boot failures can be logged").

## Problem

- Two classes bypass the application logger: their lines skip the `pidgey` channel and
  format, and ignore `LOG_LEVEL`.
- `StaticFileHandler` writes every 404 unconditionally, while
  `request-handler/static-files.md` says it is a **debug** line.
- `request-handler/request-response/errors.md` says the real error of a `500` is logged
  "through Monolog" at `error`; today it goes through `error_log()`.
- `FrontController::run()` builds its `ErrorFormatter` before the configuration is
  validated, so it cannot simply call `LoggerFactory::fromEnv()`: an invalid `LOG_LEVEL`
  makes that throw, on the very path that must report `LOG_LEVEL: invalid level`.

## Expected Behavior

- `ErrorFormatter` and `StaticFileHandler` log through a PSR-3 `LoggerInterface`.
- `FrontController::run()` builds **one** logger per request, before validating the
  configuration, and shares it with the `ErrorFormatter` and the request's `Container`.
- An invalid `LOG_LEVEL` does not prevent logging: the boot-refusal line is still written,
  naming the failures only, never values.
- Log levels:
  - an unhandled `Throwable` (the `500` path, boot refusal included) → `error`;
  - a static-file 404 → `debug` (hidden at the default `LOG_LEVEL=info`, by design of
    `static-files.md`).
- Message texts are unchanged:
  - `Unhandled <class>: <message> in <file>:<line>`;
  - `StaticFileHandler: not served: <path>`, control characters still escaped.

## Solution

### Logger at boot

- In `FrontController::run()`:
  - build the logger first with `LoggerFactory::fromEnv()`;
  - on `InvalidArgumentException` (invalid `LOG_LEVEL`), fall back to
    `LoggerFactory::create(LoggerFactory::DEFAULT_LEVEL)`. `ConfigValidator` then reports
    `LOG_LEVEL: invalid level` and the refusal is logged at `error` through that fallback;
  - pass the logger to `new ErrorFormatter(logger: ...)` and to the container factory
    (`new Container(logger: $logger)`), so `Container::logger()` never rebuilds it.

### `ErrorFormatter`

- Constructor `(?WallClock $clock = null, ?LoggerInterface $logger = null)`. The default
  logger is `LoggerFactory::create(LoggerFactory::DEFAULT_LEVEL)` (an `ErrorLogHandler`), so
  a bare `new ErrorFormatter()` keeps writing to the same destination as today.
- Logs the unhandled error with `$logger->error(<same message>)`, no context (no
  `exception` key, so no trace and no argument values).
- Drop the "Monolog replaces it" docblock note.

### `StaticFileHandler`

- Constructor `(?string $baseDir = null, ?LoggerInterface $logger = null)`, same default
  logger as above.
- Overrides `build(Container $c)` to pass `$c->logger()`. The container already holds the
  boot logger, so no database or other service is touched (`static-files.md`: "no
  `Container` service beyond the logger built at boot").
- Logs the 404 with `$logger->debug(<same message>)`.

### Tests

- Every test that injects a callable (`ErrorFormatterTest`, `StaticFileHandlerTest`,
  `FrontControllerTest`, `ProcessingRequestTest`, `AdminCheckTest`, `GateTest`, and the
  support test cases) switches to `Pidgey\Tests\Support\CapturedLog` (or Monolog's
  `TestHandler`), asserting level and message.
- New: `FrontController::run()` with an invalid `LOG_LEVEL` answers the `500` envelope and
  logs the failure names at `error`, with no value.
- New: a static 404 is logged at `debug` and not written at `info`.

### Specs (product-owner)

- `architecture/configuration.md#logging`: an invalid `LOG_LEVEL` is reported through a
  fallback logger at the default level.
- `request-handler/front-controller.md#bootstrap`: the boot logger is shared with the
  `Container`.

### Out of scope

- The request-logging step (one `info` line per request): still a later step.
- The last-resort fallback for a logger, `ErrorFormatter` or sender failure
  (`front-controller.md#failures-outside-the-wrapper`).

### Agents

backend (implementation), product-owner (the two spec notes).

## Benefits

- One logging path: every line has the `pidgey` channel and format and honours `LOG_LEVEL`.
- Static-file 404 noise disappears from production logs at the default level, as specified.
- Boot failures, an invalid `LOG_LEVEL` included, are still logged.
