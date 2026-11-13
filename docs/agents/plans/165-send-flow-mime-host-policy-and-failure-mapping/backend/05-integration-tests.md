# 05 — Send flow integration and canary tests

Spec: `email/testing.md` (required coverage, canary password).

- `source/tests/integration/Email/SendFlowTest.php`: a real `SmtpTransport` (#173), a real
  `PasswordCipher` with test keys, `SMTP_ALLOW_PRIVATE_HOSTS` behavior through an injected
  `HostPolicy(allowPrivate: true)`, and `CapturedLog`.
  - **Mailpit:**
    - success over plain, `STARTTLS` and implicit TLS; the delivered message is checked
      through the `Mailpit` API (headers, the UTF-8 subject decoded, multipart);
    - wrong credentials → `SMTP_AUTH_FAILED`;
    - no credentials on the auth instance (`530`) → `SMTP_AUTH_FAILED` "requires
      authentication".
  - **`FakeSmtpServer`:** at least one case per transport failure code:
    - `CONNECTION_FAILED` (closed port);
    - `TLS_FAILED` (no `STARTTLS` advertised);
    - `REJECTED` (a `550` `RCPT`);
    - `TEMPORARY_FAILURE` (`421` greeting);
    - `PROTOCOL_ERROR` (malformed reply);
    - `TIMEOUT` (silent server, with short `SessionSettings`).
  - Each outcome goes through `SmtpFailureException::fromOutcome` + `ErrorFormatter`, and
    the JSON is asserted exactly.
  - Send-time codes: `allowPrivate: false` with `127.0.0.1` → `SMTP_HOST_NOT_ALLOWED`; the
    fake server records no connection (`assertClean` or the connection count).
- **Canary:** a distinctive password (for example `canary-Pw-165-é`) on the success
  path and every failure path. Assert that neither it, `base64(password)` nor the base64
  `AUTH PLAIN` payload appears in:
  - the outcome;
  - the exception message;
  - the formatted JSON;
  - any `CapturedLog` record (message and context, JSON-encoded).

  Include a fake server that echoes the `AUTH` line back in its `535` reply, to prove echo
  redaction.

## Files to Change

- `source/tests/integration/Email/SendFlowTest.php`: new.
- `source/tests/support/*`: only if a small helper is missing (for example a fake-server
  script that echoes the `AUTH` line); prefer the existing scripted replies.
