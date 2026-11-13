# ErrorFormatter on LoggerInterface

Replace the `Closure $logger` of `Pidgey\Http\ErrorFormatter` with a
`Psr\Log\LoggerInterface`.

- Constructor `(?WallClock $clock = null, ?LoggerInterface $logger = null)`; default logger
  `LoggerFactory::create(LoggerFactory::DEFAULT_LEVEL)`, so a bare `new ErrorFormatter()` keeps
  writing where `error_log()` writes today.
- `format()` logs an unhandled `Throwable` with `$this->logger->error(sprintf('Unhandled %s: %s
  in %s:%d', ...))` — the same text as today, no context array.
- Remove the docblock note "Monolog replaces it once logging is wired"; update the `@param`.
- `ErrorFormatterTest`: inject `CapturedLog::logger()`; assert one record at `ERROR` with the
  exact message for a plain `Throwable`, and no record for an `HttpException`.

## Files to Change

- `source/lib/Http/ErrorFormatter.php` — logger type, default, `error()` call, docblock.
- `source/tests/unit/Http/ErrorFormatterTest.php` — `CapturedLog` instead of the callable;
  level + message assertions.
