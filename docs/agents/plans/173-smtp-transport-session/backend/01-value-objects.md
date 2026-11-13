# Value objects, enums and failure

Add the transport's input and output types in `source/lib/Smtp/`, as listed in
[plan.md](../plan.md#shared-contracts).

**Enums**

- `TlsMode`: `Plain`, `StartTls`, `Implicit`.
- `SmtpStep`: `Connect`, `Greeting`, `Ehlo`, `StartTls`, `Auth`, `MailFrom`, `RcptTo`,
  `Data`, `EndOfData`.
- `FailureCode`: string-backed, values equal to the `SMTP_*` names.

**Value objects**

- `Target`: `final readonly` (`host`, `address`, `port`, `tlsMode`). It does no validation;
  the send flow passes checked values.
- `Credentials`: `username` plus `SecretString $password`, with the constructor parameter
  marked `#[SensitiveParameter]`.
  - Accessors `username()` and `password(): SecretString`.
  - It implements neither `__toString` nor `JsonSerializable`.
  - `__debugInfo` returns the username and `[redacted]`.
  - `__serialize` and `__clone` throw, as `SecretString` does.
- `Envelope`: `from` and `recipients`. An empty recipient list throws
  `InvalidArgumentException` with a fixed message.
- `SmtpResult`: `reply(): Reply`, plus a `code(): int` shortcut.

**`SmtpFailure`** (`final`, extends `RuntimeException`)

- The constructor is `(FailureCode $code, SmtpStep $step, ?Reply $reply = null,
  ?string $reason = null, ?Throwable $previous = null)`.
- The message is `sprintf('SMTP %s: %s', $step->name, $code->value)`, which is fixed text
  only.
- `reason` is kept only for `SMTP_TLS_FAILED`.

**Unit tests** in `source/tests/unit/Smtp/`, one file per class where there is behavior:

- `SmtpFailure`: the message format, and that it never contains the reply text;
- `Credentials`: `__debugInfo` and `print_r` / `var_dump` output have no password,
  serialization and cloning throw, and a cast to string throws an `Error`;
- `Envelope`: the empty-recipient refusal;
- `FailureCode`: the values.

## Files to Change

- `source/lib/Smtp/TlsMode.php`: new.
- `source/lib/Smtp/SmtpStep.php`: new.
- `source/lib/Smtp/FailureCode.php`: new.
- `source/lib/Smtp/Target.php`: new.
- `source/lib/Smtp/Credentials.php`: new.
- `source/lib/Smtp/Envelope.php`: new.
- `source/lib/Smtp/SmtpResult.php`: new.
- `source/lib/Smtp/SmtpFailure.php`: new.
- `source/tests/unit/Smtp/SmtpFailureTest.php`, `CredentialsTest.php`, `EnvelopeTest.php`:
  new.
