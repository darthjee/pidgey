# Send flow and SMTP connections

Turn steps 5–7 of the send flow into a step-by-step protocol spec, each step with its
success and failure mapping.

- `email/send-flow.md`:
  - send-time checks before connecting: `SMTP_INSECURE_CREDENTIALS`, then password decrypt
    (`SMTP_PASSWORD_UNREADABLE`);
  - connect / greeting under the connect timeout; greeting mapping (Decision 4);
  - `EHLO` with `SMTP_EHLO_NAME` or the local address literal, never `HELO` (Decision 12);
  - `STARTTLS` rules, no downgrade, re-`EHLO`, injection guard (Decision 1);
  - `AUTH` (Decision 2); `MAIL FROM` / `RCPT TO` / `DATA` with dot-stuffing, no `SIZE`, no
    `PIPELINING`; success = reply to the final `.`; best-effort `QUIT` (Decisions 4, 13);
  - reply parsing, limits, malformed replies, reply text (Decision 4);
  - timeouts referencing the three keys, the session deadline and the no-`DATA`-timeout
    trade-off (Decision 5);
  - log line contents and secret hygiene (Decision 9).
- `email/smtp-connections.md`: `AUTH` mechanisms (`PLAIN` then `LOGIN`), credentials
  require `tls` / `ssl`, TLS verification policy (Decision 7), resolving its TLS pending
  decision.

## Files to Change
- `docs/agents/specs/email/send-flow.md` — protocol steps, timeouts, logging.
- `docs/agents/specs/email/smtp-connections.md` — AUTH, credentials/encryption, TLS policy.
