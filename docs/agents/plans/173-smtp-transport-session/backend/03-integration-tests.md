# Integration and canary tests

End-to-end coverage of the transport against real sockets. Add
`source/tests/integration/Smtp/SmtpTransportTest.php`, following the patterns of
`SocketStreamTest` (the `Mailpit` and `FakeSmtpServer` support classes, `SMTP_CA_FILE`
resolved via `AppPath`, the `SystemClock`).

## Mailpit (`Mailpit::plain()`, `starttls()`, `ssl()`)

- **Delivery:** `none` without credentials, `StartTls` and `Implicit` with
  `Mailpit::AUTH_USER` / `AUTH_PASSWORD`. Each sends a small CRLF message with a line
  starting with `.`, and the test checks:
  - the result `code()` is `250`;
  - the message is in the mailbox (`Mailpit::messages()`);
  - the dot-stuffed line arrived as one `.`.

  Clear the mailbox in `setUp`.
- **Wrong password** on `StartTls` → `SMTP_AUTH_FAILED`, step `Auth`, with a reply.
- **`530`:** if the Mailpit instance requires auth, no credentials on the auth instance →
  `SMTP_AUTH_FAILED`, step `MailFrom`, with a reply.
- **Port / encryption mismatch:**
  - `Implicit` against the plain port → `SMTP_TLS_FAILED`;
  - `Plain` against the ssl port with a short connect timeout → `SMTP_TIMEOUT`.

## `FakeSmtpServer` scenarios

- `STARTTLS` not advertised.
- `AUTH` advertising only `CRAM-MD5`.
- A multi-line `250` final reply.
- A malformed reply.
- A `451` at `RCPT TO`.
- `then: inject` after the `220` to `STARTTLS`.
- `cert: untrusted` and `cert: wrong-name` with implicit TLS and with `STARTTLS` →
  `SMTP_TLS_FAILED`, with a non-null `reason()`.
- `then: hang` at the greeting → `SMTP_TIMEOUT`, using a short timeout.
- `then: close` before the greeting → `SMTP_CONNECTION_FAILED`.
- A connection to a closed port → `SMTP_CONNECTION_FAILED`.

Call `assertClean()` on each server.

## Coverage matrix

Each of the seven `FailureCode` values must be reached by at least one test in this file.
Add a data-provider-driven check, or a comment table, that maps each code to its test.

## Canary

Run the Mailpit wrong-password case and the `FakeSmtpServer` `AUTH` cases with a canary
password. Assert that the caught `SmtpFailure` message and the messages of its `previous`
chain contain neither the password nor its base64 forms (alone, or inside the `PLAIN`
payload).

## Files to Change

- `source/tests/integration/Smtp/SmtpTransportTest.php`: new.
