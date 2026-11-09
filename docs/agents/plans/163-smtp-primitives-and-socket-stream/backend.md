# Backend Plan: SMTP primitives and socket stream

Main plan: [plan.md](plan.md)

## Shared contracts

The backend implements every contract of [plan.md](plan.md#shared-contracts): `SecretString`,
`Runtime`, `AppPath`, the configuration constants, failure lines and `SessionSettings`, the
`Pidgey\Smtp\` primitives, `TlsContext`, `SocketStream`, `FakeSmtpStream`, `FakeSmtpServer`
and the regenerated TLS fixtures. It relies on the product owner only for the spec text;
no code depends on the spec changes.

## Steps

- [01 — SecretString and runtime hardening](backend/01-secret-string-and-runtime.md)
- [02 — SMTP settings: validation and SessionSettings](backend/02-smtp-settings.md)
- [03 — Deadline, Reply, ReplyParser and EhloExtensions](backend/03-replies-and-deadline.md)
- [04 — Stream interface, failures, TlsContext and FakeSmtpStream](backend/04-stream-contract-and-fake.md)
- [05 — IP SAN fixture and FakeSmtpServer](backend/05-fake-smtp-server.md)
- [06 — SocketStream and its integration tests](backend/06-socket-stream.md)

## CI Checks

- `source`: `make tests` (CI job: `backend_tests`) — needs the Mailpit test instances
  (`docker compose` `tests` service locally).
- `source`: `composer lint` (phpcs + phpmd unused-code gate; CI lint job).

## Notes

- Never disable verification anywhere, tests included; the guard test of step 06 enforces it.
- Timeout tests use real waits of 0.2–0.5 s with loose bounds (elapsed ≥ timeout and
  < timeout + 1 s); keep them few.
- `var_export()` exposes `SecretString`'s private property: accepted, documented gap (never
  called by the app); do not try to work around it.
- `SMTP_ALLOW_PRIVATE_HOSTS` and `SMTP_TEST_*` are out of scope (send flow and limit issues).
- The email agent reviews steps 03, 04 and 06 (reply parsing, `EHLO`, `STARTTLS` pending
  bytes, close-with-no-reply).
