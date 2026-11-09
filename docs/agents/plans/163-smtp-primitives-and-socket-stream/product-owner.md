# Product Owner Plan: SMTP primitives and socket stream

Main plan: [plan.md](plan.md)

## Shared contracts

Record in the specs the names and rules of [plan.md](plan.md#shared-contracts) that the
specs mention: `Pidgey\Infra\SecretString` (its methods and the "neither `__toString` nor
`JsonSerializable`" rule), `Pidgey\Infra\Runtime::harden()`, relative `SMTP_CA_FILE`
resolution against the app root, the timeout validation precedence,
`Pidgey\Smtp\SessionSettings` / `Container::smtpSessionSettings()`, `TlsContext`, the
`FakeSmtpStream` time model, the `FakeSmtpServer` scenario format and the `server.pem` IP
SAN. Use "proposal" wording only where the specs already do.

## Implementation Steps

### Step 1 — Fix the contradictions and gaps

- `email/send-flow.md#secret-hygiene` item 2: `Pidgey\Infra\SecretString`; implements
  neither `__toString` nor `JsonSerializable` (a cast throws); `__debugInfo` redacted;
  `__serialize` / `__clone` throw; `reveal()` and `wipe()` (`sodium_memzero()`, then
  `reveal()` throws); link to `architecture/backend.md#logging`. Item 5 points at `wipe()`.
- `email/send-flow.md#replies`: a close **with no reply at all** — at the greeting
  `SMTP_CONNECTION_FAILED`, at any later step `SMTP_PROTOCOL_ERROR`, during `QUIT` ignored;
  the line limit counts content without CRLF; empty line malformed; `NNN` / `NNN-` valid;
  the enhanced status code is kept only when `X` equals the code's first digit.
  `email/send-flow.md#ehlo`: first line is the greeting name; `AUTH` and `AUTH=` merged.
  `#timeouts`: the command timeout bounds the **whole reply** (not each line) and each write.
- `email/failures.md#classification-rules`: the close-with-no-reply rule.
- `architecture/backend.md#layers`: `SecretString` moves from the Features row to the
  Infrastructure row; the SMTP transport row and the dependency sentence say
  `Pidgey\Smtp\` depends only on `Pidgey\Infra\` (`Clock`, `SecretString`).
- `implement-email/transport.md`: proposed names (`Pidgey\Infra\SecretString`, plus
  `SessionSettings`, `EhloExtensions`, `TlsContext`, `StreamFailure`), item 6, and the
  Stream contract (`UnexpectedData`, per-reply budget); note that #165 wraps `decrypt()`'s
  `string` into `SecretString` immediately.

### Step 2 — Configuration and testing specs, ticks

- `architecture/configuration.md#smtp-transport`: relative `SMTP_CA_FILE` resolved against
  the app root (`source/`); timeout precedence (invalid values skipped by `exceeds`,
  effective values compared); `Runtime::harden()` called by `public/index.php` and every
  `bin/*` entry point (unset `PHP_ENV` = production); settings exposed as `SessionSettings`
  through `Container::smtpSessionSettings()`.
- `email/testing.md`: fixture table (`server.pem` SAN adds `IP:127.0.0.1`); `FakeSmtpStream`
  time model (delays advance the frozen clock; a delay beyond the budget is a timeout);
  `FakeSmtpServer` scenario format, `PORT=` handshake, exit status asserted in teardown, the
  script path; real-wait timeout tests (0.2–0.5 s, loose bounds); the guard tests (no
  disabled verification, `SecretString` leaks, `var_export` gap).
- `testing/backend.md`: support-class rows for `FakeSmtpStream` / `FakeSmtpServer` (the
  script path) and the guard tests.
- Tick the delivered items: `implement-email/transport.md` items 1, 2, 3, 6, 10;
  `implement-email/testing.md` `FakeSmtpStream`, `FakeSmtpServer` and its script, and any
  other item this issue delivers.

## Files to Change

- `docs/agents/specs/email/send-flow.md` — secret hygiene, replies, `EHLO`, timeouts.
- `docs/agents/specs/email/failures.md` — classification rule.
- `docs/agents/specs/architecture/backend.md` — layers.
- `docs/agents/specs/architecture/configuration.md` — SMTP transport section.
- `docs/agents/specs/email/testing.md` — fixtures, fakes, guard tests.
- `docs/agents/specs/testing/backend.md` — support classes.
- `docs/agents/specs/implement-email/transport.md`,
  `docs/agents/specs/implement-email/testing.md` — names, contract, ticks.

## Notes

- Permanent specs win: update them, and keep the `implement-email/` notes as pointers.
- Do not tick items owned by #173 (transport item 4, item 11's transport tests) or #165.
