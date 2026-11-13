# Backend Plan: Move ErrorFormatter and StaticFileHandler onto the Monolog logger

Main plan: [plan.md](plan.md)

## Shared contracts

- Produce the boot logger in `FrontController::run()` (`fromEnv()`, falling back to
  `LoggerFactory::create(LoggerFactory::DEFAULT_LEVEL)` on `InvalidArgumentException`), shared
  with the `ErrorFormatter` and every `Container` of the request.
- `ErrorFormatter(?WallClock $clock = null, ?LoggerInterface $logger = null)`: `error`, message
  `Unhandled <class>: <message> in <file>:<line>`, no context.
- `StaticFileHandler(?string $baseDir = null, ?LoggerInterface $logger = null)` +
  `build(Container $c)` with `$c->logger()`: `debug`, message
  `StaticFileHandler: not served: <path>` (escaped).
- Default logger of both constructors: `LoggerFactory::create(LoggerFactory::DEFAULT_LEVEL)`.

## Steps

- [01 — ErrorFormatter on LoggerInterface](backend/01-error-formatter.md)
- [02 — StaticFileHandler on LoggerInterface](backend/02-static-file-handler.md)
- [03 — Boot logger in FrontController::run()](backend/03-boot-logger.md)
- [04 — Migrate the remaining tests](backend/04-migrate-tests.md)

## CI Checks

- `source/`: `make tests` (`composer lint && composer tests`; CI jobs: `backend_checks`,
  `backend_tests`)

## Notes

- With the default `LOG_LEVEL=info`, static-file 404 lines are no longer written; this is the
  behavior `request-handler/static-files.md` specifies (debug line).
- Never add an `exception` key to the log context: it would bring a trace and argument values.
- `LineFormatter` already flattens line breaks; keep the `addcslashes` escape of the static
  path anyway (NUL and other control characters).
