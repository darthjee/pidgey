# Product-owner Plan: SMTP transport session

Main plan: [plan.md](plan.md)

## Shared contracts

Use the names the backend delivers (see [plan.md](plan.md#shared-contracts)) to replace
"proposal" wording where they are now settled:

- the `Pidgey\Smtp\` names: `Transport`, `SmtpTransport`, `SmtpFailure`, `FailureCode`,
  `SmtpStep`, `TlsMode`, `Target`, `Credentials`, `Envelope` and `SmtpResult`;
- `Container::smtpTransport()`.

## Implementation Steps

### Step 1 — Tick the transport checklist

In `docs/agents/specs/implement-email/transport.md`:

- **Item 4:** mark it `[x]` and list the delivered names.
- **Building block: SMTP transport:** update the "Proposed names" bullet so it lists the names
  delivered by #173 next to those delivered by #163.
- **Result contract:** add a sentence saying the transport returns the raw `Reply`, and that
  redaction, sanitization and the enhanced-status join are done by the send flow (#165).
- **Item 11:** leave it `[ ]`, with a note: "Transport: reply-by-reply unit tests, Mailpit and
  `FakeSmtpServer` integration tests and the exception-message canary check are delivered by
  #173. The leak test through response bodies and log records, and the HTTP columns of the
  failure mapping, follow with #165."

### Step 2 — Record the line-ending rule

In `docs/agents/specs/email/send-flow.md#envelope-and-data`, add to the `DATA` bullet:

> The transport splits the message on CRLF, bare CR or bare LF and rewrites every line with
> CRLF before dot-stuffing, so a bare CR or LF never reaches the wire (SMTP smuggling).

In `docs/agents/specs/architecture/backend.md#layers`, the `Pidgey\Smtp\` row: name
`Transport` / `SmtpTransport` and the `TlsMode` mapping. The send flow maps the model's
`none` / `tls` / `ssl` onto `Plain` / `StartTls` / `Implicit`; the transport never takes
`Pidgey\Model\SmtpEncryption`.

## Files to Change

- `docs/agents/specs/implement-email/transport.md`: item 4 ticked, item 11 note, delivered
  names, raw-reply note.
- `docs/agents/specs/email/send-flow.md`: the line-ending rule in `DATA`.
- `docs/agents/specs/architecture/backend.md`: the `Pidgey\Smtp\` layer row.

## Notes

- Specs only; no code. Do not tick item 11.
