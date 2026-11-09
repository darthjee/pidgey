# Product Owner Plan: Logger and clock foundation

Main plan: [plan.md](plan.md)

## Shared contracts

The product-owner agent relies on the backend agent's names from
[plan.md](plan.md#shared-contracts):
- `LoggerFactory::create()` / `fromEnv()`, channel `pidgey`, no processors, stack traces off;
- `Container::clock()` and `Container::logger()`;
- `FrozenClock`, `FrozenWallClock` and `CapturedLog`.

Use them verbatim.

## Implementation Steps

### Step 1 — Clock vs. WallClock wording

Name `Clock` only where monotonic time is meant (the SMTP session deadline), and
`WallClock` wherever wall time is meant:

- **`implement-email/transport.md`:**
  - the send-flow collaborators: `Pidgey\Infra\Clock` (`Date` header) becomes
    `Pidgey\Infra\WallClock` (`Date` header);
  - the transport collaborators and the "Session deadline" step stay on `Clock`, but say
    "monotonic `Pidgey\Infra\Clock`" once.
- **`implement-email/endpoints.md`:** the endpoints collaborators (around line 79) and the
  test-email collaborators (around line 137) change from `Pidgey\Infra\Clock` to
  `Pidgey\Infra\WallClock`, since the limit window is stored in the database.
- **`email/testing.md`:**
  - the frozen clock of the transport unit tests (around line 113) becomes `FrozenClock`;
  - the frozen clock of the test-email limit (around line 169) becomes `FrozenWallClock`.
- **`testing/backend.md#email-test-support`:**
  - add rows for `Pidgey\Tests\Support\FrozenClock` (a `Clock` whose `sleep()` advances
    `now()`) and `Pidgey\Tests\Support\FrozenWallClock` (a UTC `WallClock` with
    `advance()`);
  - reword the `CapturedLog` row: built through `LoggerFactory::create('debug', TestHandler)`
    and injected with `new Container(logger: …)`, with `assertNotLogged()` checking the
    plain and base64 forms.
- **`architecture/backend.md`:** the Infrastructure row also names the monotonic `Clock`
  next to `WallClock`.
- **`request-handler/handlers.md`:** the container getter examples add `clock(): Clock`.

### Step 2 — Logging rules and checklist

- **`architecture/backend.md#logging`:**
  - `LoggerFactory::create(level, handler = ErrorLogHandler)` and `fromEnv()`;
  - channel `pidgey`, line format `pidgey.<LEVEL>: <message> <context> <extra>`;
  - line breaks flattened, no processors, no stack traces;
  - `Container::logger()` is built on first use.
  - The object-normalization rule: a context object is rendered as JSON
    (`JsonSerializable`), as `__toString()`, or else as its class name. A secret-holding
    value object (e.g. `SecretString`) must therefore implement neither interface.
- **`implement-email/testing.md`:** tick item 5's `CapturedLog`.
- **`implement-email/endpoints.md`**, "Implementation work":
  - item 6 (Logger): note that `LoggerFactory` and the container wiring landed in #162, and
    the operator log lines remain;
  - item 7 (Configuration): note that `LOG_LEVEL` landed in #162.

## Files to Change

- `docs/agents/specs/implement-email/transport.md`: `WallClock` for the `Date` header;
  `Clock` named as monotonic.
- `docs/agents/specs/implement-email/endpoints.md`: `WallClock` in both collaborator lists;
  notes on implementation-work items 6 and 7.
- `docs/agents/specs/implement-email/testing.md`: tick `CapturedLog`.
- `docs/agents/specs/email/testing.md`: `FrozenClock` / `FrozenWallClock`.
- `docs/agents/specs/testing/backend.md`: two new rows; the `CapturedLog` row reworded.
- `docs/agents/specs/architecture/backend.md`: the Infrastructure row; the Logging section.
- `docs/agents/specs/request-handler/handlers.md`: the `clock()` getter example.

## Notes

- Do not change `deployment/server.md`, `deployment/variables.md` or
  `architecture/configuration.md`; they already describe `LOG_LEVEL` correctly.
- Line numbers are approximate; match by content.
