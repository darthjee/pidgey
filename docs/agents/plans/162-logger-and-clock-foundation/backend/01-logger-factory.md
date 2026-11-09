# LoggerFactory

Add `Pidgey\Infra\LoggerFactory`, exactly as in the
[shared contracts](../plan.md#shared-contracts).

- **`create(string $level, ?HandlerInterface $handler = null)`:**
  1. If `$level` is not in `self::LEVELS` (strict `in_array`), throw an
     `InvalidArgumentException('LOG_LEVEL: invalid level')`.
  2. Default the handler to `new ErrorLogHandler()`.
  3. If the handler is an `AbstractHandler`, call `setLevel(Level::fromName($level))`.
  4. If the handler is a `FormattableHandlerInterface`, set
     `new LineFormatter(self::FORMAT)` on it, with `allowInlineLineBreaks` false (line
     breaks flattened to spaces) and `includeStacktraces` false.
  5. Return `new Logger(self::CHANNEL, [$handler])`, with no processors.
- **`fromEnv()`:** `self::create(Env::get('LOG_LEVEL', self::DEFAULT_LEVEL))`. `Env::get`
  already maps an empty value to the default.
- Document on the class that no processors are added and stack traces are off, and why:
  nothing request-derived leaks, and traces may carry argument values.

**Tests (`tests/unit/Infra/LoggerFactoryTest.php`):**

- **Levels:**
  - each of the 8 levels is accepted (data provider);
  - with `info`, a `debug` record is not handled and an `info` record is;
  - `INFO`, ` info` and `warn` throw, and the message is `LOG_LEVEL: invalid level`
    without the value.
- **Channel and format:**
  - the channel is `pidgey`;
  - a message with `\n` in it, and a context value with `\n` in it, both format onto one
    line.

  Check these through a `TestHandler` passed to `create()`, or through the default
  handler's formatter (`format($record)`). Never write to the real `error_log`.
- **Defaults:**
  - with no handler, the logger's single handler is an `ErrorLogHandler`;
  - `fromEnv()` uses `info` when `LOG_LEVEL` is unset or empty, and the given level
    otherwise.

## Files to Change

- `source/lib/Infra/LoggerFactory.php`: new.
- `source/tests/unit/Infra/LoggerFactoryTest.php`: new.
