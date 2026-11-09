# Backend Plan: Logger and clock foundation

Main plan: [plan.md](plan.md)

## Shared contracts

The backend agent produces every contract in [plan.md](plan.md#shared-contracts):
- `LoggerFactory`, with its constants, `create()` and `fromEnv()`;
- the `Container` constructor and its `clock()` and `logger()` accessors;
- the `LOG_LEVEL` rule in `ConfigValidator`;
- the `FrozenClock`, `FrozenWallClock` and `CapturedLog` test support classes.

The product-owner agent documents them, so the names must match exactly.

## Steps

- [01 — LoggerFactory](backend/01-logger-factory.md)
- [02 — LOG_LEVEL validation](backend/02-log-level-validation.md)
- [03 — Container clock() and logger()](backend/03-container-accessors.md)
- [04 — Test support: frozen clocks and CapturedLog](backend/04-test-support.md)

## CI Checks

- `source/`: `make tests` (CI job: `backend_tests`). It runs `composer lint` (phpcs plus the
  phpmd unused-code gate) and `composer tests` (both PHPUnit suites).

## Notes

- `monolog/monolog ^3.9` is already in `require` and `composer.lock`. Do not touch Composer.
- Monolog's `Level::fromName()` is case-insensitive. Check against `LoggerFactory::LEVELS`
  before converting, so `INFO` is rejected.
- Do not move `ErrorFormatter` or `StaticFileHandler` onto the logger; that is #171.
- Do not replace existing tests' ad-hoc `fixedClock(...)` helpers. Only new tests use the
  shared doubles.
- Tests that set `LOG_LEVEL` through `putenv` must restore it in `tearDown`, the way
  `ConfigValidatorTest` already does for its keys.
