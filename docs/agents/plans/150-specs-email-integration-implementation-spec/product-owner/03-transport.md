# Transport detail file

Create `docs/agents/specs/implement-email/transport.md` (owner #152).

Building blocks:
- **SMTP transport** — opens the connection (`ssl` implicit TLS / `tls` STARTTLS / `none`),
  `EHLO`, `AUTH`, `MAIL FROM` / `RCPT TO` / `DATA`, `QUIT`; proposed new `Pidgey\Email\`
  namespace. Collaborators: `SmtpConnection`, `SmtpEncryption`, the encryption service,
  `Clock`.
- **Send flow** — builds the MIME message, runs the transport, maps the outcome to
  `SMTP_*` errors, logs the attempt. Collaborators: monolog operator log, `ErrorResponse` /
  `HttpException` family.
- Governing specs: `email/send-flow.md` (steps 5–8), `email/failures.md`,
  `email/message-scope.md`, `api/conventions/errors.md#smtp-send-failures`.

Shared contracts (what each carries):
- transport interface: input = connection settings + decrypted credentials + envelope
  (sender, recipients) + message bytes; output = final SMTP reply or a failure;
- stream abstraction the transport writes/reads through, so a fake SMTP server (unit,
  in-memory) or a socket fixture (integration) plugs in;
- result value: SMTP reply code + sanitized message;
- failure value: failure code (`SMTP_CONNECTION_FAILED`, `SMTP_TLS_FAILED`,
  `SMTP_AUTH_FAILED`, `SMTP_REJECTED`, `SMTP_TEMPORARY_FAILURE`, `SMTP_TIMEOUT`) + optional
  SMTP reply; mapping to HTTP status per `failures.md`;
- configuration keys: connect / per-command timeouts, CA bundle path.

Open items (`**Pending decision:**`):
- `tls` without `STARTTLS` advertised (no downgrade);
- supported AUTH mechanisms and the code when none / no AUTH is offered;
- port/encryption mismatch behavior;
- multi-line reply parsing and the text fed to `error.smtp.message`;
- timeout keys and defaults vs PHP `max_execution_time` / Apache timeout;
- IDN hostnames and non-ASCII addresses (punycode / SMTPUTF8 / reject);
- TLS certificate verification policy — *working assumption, to be confirmed in #152*:
  always verify, peer name = `host`; custom CA configuration;
- `failures.md` pending items: `error.smtp.message` max length, fixed-message wording, HTTP
  status mapping;
- no `AUTH` payload or decrypted password in exceptions, debug output or logs.

## Files to Change
- `docs/agents/specs/implement-email/transport.md` — new detail file.
