# Plan: Move ErrorFormatter and StaticFileHandler onto the Monolog logger

Issue: [171-move-errorformatter-and-staticfilehandler-onto-the-monolog-logger.md](../../issues/171-move-errorformatter-and-staticfilehandler-onto-the-monolog-logger.md)

## Overview

`FrontController::run()` builds one Monolog logger per request before the configuration is
validated, falling back to the default level when `LOG_LEVEL` is invalid, and shares it with
the `ErrorFormatter` and the request's `Container`. `ErrorFormatter` and `StaticFileHandler`
drop their `error_log()` callable for a PSR-3 `LoggerInterface` (unhandled error → `error`,
static 404 → `debug`, message texts unchanged). The product owner records the fallback logger
and the shared boot logger in the specs.

## Agents involved

- [backend](backend.md)
- [product-owner](product-owner.md)

## Shared contracts

- **Boot logger** (built in `FrontController::run()`, first thing):
  - `LoggerFactory::fromEnv()`;
  - on `InvalidArgumentException` → `LoggerFactory::create(LoggerFactory::DEFAULT_LEVEL)`
    (`info`, `ErrorLogHandler`). `ConfigValidator` then reports `LOG_LEVEL: invalid level`,
    logged at `error` through the fallback; names only, never values.
  - The same instance goes to `new ErrorFormatter(logger: $logger)` and to every
    `new Container(logger: $logger)` built by the default container factory.
- **`ErrorFormatter::__construct(?WallClock $clock = null, ?LoggerInterface $logger = null)`**:
  default logger `LoggerFactory::create(LoggerFactory::DEFAULT_LEVEL)`. Unhandled `Throwable`
  → `$logger->error('Unhandled <class>: <message> in <file>:<line>')`, no context.
- **`StaticFileHandler::__construct(?string $baseDir = null, ?LoggerInterface $logger = null)`**:
  same default logger; `build(Container $c)` passes `$c->logger()`. Rejected path →
  `$logger->debug('StaticFileHandler: not served: <path>')`, control characters escaped as
  today.
- Out of scope: the per-request `info` logging step; the last-resort hard-coded `500`
  fallback (`front-controller.md#failures-outside-the-wrapper`).
