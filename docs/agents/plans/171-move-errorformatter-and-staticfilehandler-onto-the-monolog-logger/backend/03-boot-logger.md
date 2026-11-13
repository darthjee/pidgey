# Boot logger in FrontController::run()

Build the application logger first in `FrontController::run()` and share it.

- Add a private static `bootLogger(): LoggerInterface`: `LoggerFactory::fromEnv()`, catching
  `InvalidArgumentException` and returning `LoggerFactory::create(LoggerFactory::DEFAULT_LEVEL)`.
- `run()`:
  - `$logger = self::bootLogger();` before anything else;
  - `$errorFormatter = new ErrorFormatter(logger: $logger);`
  - construct the controller with a container factory
    `static fn (): Container => new Container(logger: $logger)`.
- The validation failure keeps going through `assertValidConfiguration()` →
  `RuntimeException('Invalid configuration: <names>')` → `ErrorFormatter::format()` → one
  `error` line naming the failures only.
- Update the class docblock of `run()` to mention the boot logger and its fallback.
- `FrontControllerRunTest` (separate processes): point `error_log` at a temp file instead of
  `/dev/null` in the refusal tests and assert:
  - the existing refusal test logs a line containing
    `pidgey.ERROR: Unhandled RuntimeException: Invalid configuration: SECRET_KEY: too short, DB_HOST: missing`;
  - new: `LOG_LEVEL=verbose` (otherwise valid environment) answers the `500` envelope and logs
    `pidgey.ERROR: ... Invalid configuration: LOG_LEVEL: invalid level`, and the log never
    contains `verbose`;
  - the development-key test's log never contains the key value.

## Files to Change

- `source/lib/Http/FrontController.php` — `bootLogger()`, shared logger in `run()`, docblocks.
- `source/tests/unit/Http/FrontControllerRunTest.php` — log-file assertions, invalid
  `LOG_LEVEL` test.
