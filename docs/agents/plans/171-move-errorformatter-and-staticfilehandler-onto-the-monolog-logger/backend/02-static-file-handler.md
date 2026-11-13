# StaticFileHandler on LoggerInterface

Replace the `Closure $logger` of `Pidgey\Http\Handler\StaticFileHandler` with a
`Psr\Log\LoggerInterface`, and wire it from the container.

- Constructor `(?string $baseDir = null, ?LoggerInterface $logger = null)`; default logger
  `LoggerFactory::create(LoggerFactory::DEFAULT_LEVEL)`.
- Override `public static function build(Container $container): static` to return
  `new static(null, $container->logger())` (match the `build()` signature/return style used by
  other handlers that override it). `Container::logger()` returns the boot logger injected by
  step 03, so no other service (PDO etc.) is touched.
- `notFound()` logs with `$this->logger->debug('StaticFileHandler: not served: ' .
  addcslashes($candidate, "\0..\37\177"))`.
- `StaticFileHandlerTest`: inject `CapturedLog::logger()`; assert the 404 record is at `DEBUG`
  with the escaped path; add a test that `build()` uses the container's logger (a
  `new Container(logger: $captured->logger())` with a connection factory that fails if
  called); add a test that a logger built with `LoggerFactory::create('info', <TestHandler>)`
  records nothing on a 404.

## Files to Change

- `source/lib/Http/Handler/StaticFileHandler.php` — logger type, default, `build()`,
  `debug()` call, docblocks.
- `source/tests/unit/Http/Handler/StaticFileHandlerTest.php` — `CapturedLog`; level,
  `build()` and `info`-threshold tests.
