# 03 — Reply text, outcome and send flow

Spec: `email/send-flow.md` (send-time checks, attempt log, secret hygiene),
`email/failures.md` (echo redaction, sanitization).

- `Pidgey\Email\ReplyText::clean()`: redaction, then sanitization (see the shared
  contracts).
- `Pidgey\Email\SendActor` and `Pidgey\Email\SendOutcome`: see the shared contracts.
- `Pidgey\Email\SendFlow`:
  - **Constructor:** `HostPolicy`, `PasswordCipher`, `SmtpConnectionRepository` (for
    `findEncryptedPassword`, since the model's `encryptedPassword` may already be loaded:
    use whichever the model carries and avoid a second read), `MimeBuilder`, `Transport`,
    `SessionSettings` and `LoggerInterface`.
  - **`send()`:** follows the order in the shared contracts.
    - Credentials exist when `username !== null && encryptedPassword !== null`.
    - Send-time failures return `SendOutcome::failed(code, 'send-time-check', null, null, null)`
      without touching the transport.
    - `HostCheck::unresolvable` returns `SMTP_CONNECTION_FAILED` with step `connect`, without
      touching the transport.
  - **Transport call:** map the transport's `SmtpFailure` to `SendOutcome::failed`, with the
    reply text run through `ReplyText::clean`, and a success to `SendOutcome::sent(code, clean(text))`.
  - **Wipe:** `SecretString::wipe()` in `finally`.
  - **Logging:** exactly one `smtp-send-attempt` line per call (see the shared contracts);
    `info` when sent, `warning` when failed.
  - **Secret hygiene:** mark the secret-carrying parameters `#[SensitiveParameter]`; never
    put the username or the password into exception messages.
- `Container::mimeBuilder()`, `Container::sendFlow()`.

## Files to Change

- `source/lib/Email/ReplyText.php`, `SendActor.php`, `SendOutcome.php`, `SendFlow.php`: new.
- `source/lib/Container.php`: wiring.
- `source/tests/unit/Email/ReplyTextTest.php`:
  - the password, its base64 form and the base64 `AUTH PLAIN` payload are all redacted;
  - non-ASCII and NUL bytes become `?`;
  - tabs and whitespace runs collapse;
  - the 512 cap (exactly 512 is kept; 513 becomes 509 + `...`).
- `source/tests/unit/Email/SendFlowTest.php` (fake `Transport`, stub resolver,
  `CapturedLog`):
  - the order of the send-time checks, with the transport never called (assert zero calls);
  - credentials with `none` are refused before any decrypt (a cipher spy is never called);
  - a denied host is refused before any decrypt;
  - a corrupt or unknown key gives `SMTP_PASSWORD_UNREADABLE`;
  - an unresolvable host gives `SMTP_CONNECTION_FAILED`;
  - the checked IP and `host` are passed through correctly;
  - the success outcome;
  - each failure maps to its outcome;
  - wipe is called on success and on failure (inspect the `SecretString` handed to the fake:
    `reveal()` throws afterwards);
  - one log line with the exact context, and no subject, body or password in it.
