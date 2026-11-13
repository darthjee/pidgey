# Product Owner Plan: Send flow: MIME, host policy and failure mapping

Main plan: [plan.md](plan.md)

## Shared contracts

Names from [plan.md#shared-contracts](plan.md#shared-contracts): `MimeBuilder`, `HostPolicy`
(`HostResolver`), `SendFlow`, `SendOutcome`, `SendActor`, `ReplyText`,
`SmtpFailureException`, and the log message `smtp-send-attempt`.

## Implementation Steps

### Step 1 — Spec fixes and decisions

- `email/message-scope.md#headers`: `Date` comes from `Pidgey\Infra\WallClock`, not `Clock`.
- `email/send-flow.md#attempt-log`:
  - the message `smtp-send-attempt`, `info` when sent and `warning` when failed, with the
    context field names;
  - send-time failures are logged with step `send-time-check`.
- `email/send-flow.md#host-check`: the resolver rule (A through the system resolver, which
  honors the hosts file; AAAA through DNS; an AAAA error means no AAAA).
- `architecture/backend.md#layers`: `SendOutcome` lives in `Pidgey\Email\`. Remove it from
  the `Pidgey\Model\` list.

### Step 2 — Tick the implementation items

- `implement-email/transport.md`:
  - items 5, 7 and 8 become `[x]`, with the decided names replacing the "proposed" ones in
    the send-flow building block;
  - note that `SendFlow` returns an outcome and that the HTTP mapping is
    `SmtpFailureException::fromOutcome()`.
- `implement-email/endpoints.md`: item 4 becomes `[x]`. Item 7: note that
  `SMTP_ALLOW_PRIVATE_HOSTS` is read by `Container::hostPolicy()` and is never validated.
  Leave `SMTP_TEST_*` to #166.
- Do not tick the transport items 4 and 11 (#173).

## Files to Change

- `docs/agents/specs/email/message-scope.md`
- `docs/agents/specs/email/send-flow.md`
- `docs/agents/specs/architecture/backend.md`
- `docs/agents/specs/implement-email/transport.md`
- `docs/agents/specs/implement-email/endpoints.md`
