# Product-owner Plan: Move ErrorFormatter and StaticFileHandler onto the Monolog logger

Main plan: [plan.md](plan.md)

## Shared contracts

- The boot logger: `LoggerFactory::fromEnv()`, falling back to
  `LoggerFactory::create(LoggerFactory::DEFAULT_LEVEL)` when `LOG_LEVEL` is invalid; shared
  with the `ErrorFormatter` and the request's `Container`.

## Implementation Steps

### Step 1 — Record the fallback logger

In `docs/agents/specs/architecture/configuration.md#logging`, under the `LOG_LEVEL` refusal
bullet, add: the refusal itself is still logged, through a fallback logger at the default
level (`info`), naming the failures only.

### Step 2 — Record the shared boot logger

In `docs/agents/specs/request-handler/front-controller.md#bootstrap`, item 2 (Logger): built
from `LOG_LEVEL`, falling back to the default level when it is invalid, and handed to the
`ErrorFormatter` and to the request's `Container` (so `Container::logger()` returns it). Check
that `request-handler/handlers.md` and `request-handler/static-files.md` ("the logger built at
boot") still read consistently; `request-handler/request-response/errors.md` already says the
real error is logged through Monolog.

## Files to Change

- `docs/agents/specs/architecture/configuration.md` — fallback logger note.
- `docs/agents/specs/request-handler/front-controller.md` — shared boot logger.

## Notes

- Spec-only; no code. The request-logging step and the last-resort fallback stay unchanged.
