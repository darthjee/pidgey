# Implementation spec: transport

Close #152's part of the temporary implementation spec.

- `implement-email/transport.md`: check off every open item with a link to the permanent
  spec section that now holds it; move the transport class proposals to `Pidgey\Smtp\`
  (send flow stays in `Pidgey\Email\`, plus `SecretString`); update the contracts (Failure
  codes incl. the two new ones, all fixed messages, the three timeouts + deadline, stream
  operations incl. the local address for `EHLO`); replace "New configuration keys" with the
  adopted keys; list the implementation work (reply parser, stream + socket stream with TLS
  1.2+ and CA file, session/deadline, transport, MIME builder, send flow, HTTP exception,
  validation changes, config keys).
- `implement-email/testing.md`: the reply-by-reply fake-stream tests and the canary-password
  leak test (Decision 9).

## Files to Change
- `docs/agents/specs/implement-email/transport.md` — items resolved, names, work list.
- `docs/agents/specs/implement-email/testing.md` — fake-stream and canary tests.
