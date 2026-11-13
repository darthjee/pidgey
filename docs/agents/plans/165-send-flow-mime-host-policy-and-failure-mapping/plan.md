# Plan: Send flow: MIME, host policy and failure mapping

Issue: [165-send-flow-mime-host-policy-and-failure-mapping.md](../../issues/165-send-flow-mime-host-policy-and-failure-mapping.md)

## Overview

Build the send core shared by the test email (#166) and `POST /v1/messages`:
- `Pidgey\Email\MimeBuilder`;
- `Pidgey\Smtp\HostPolicy` with an injectable resolver;
- `Pidgey\Email\SendFlow`, which runs the send-time checks, decrypts into `SecretString`,
  runs the #173 transport against the checked IP, redacts and sanitizes the reply, writes
  one attempt log line and returns a `SendOutcome`;
- `Pidgey\Http\SmtpFailureException::fromOutcome()`, which maps a failed outcome to status,
  fixed message, port hint and `error.smtp`.

**Blocked by #173:** the transport interface, `SmtpFailure` and `FailureCode` come from it.
Where this plan names them, #173's merged names win.

## Agents involved

- [backend](backend.md): all code and tests.
- [product-owner](product-owner.md): spec fix and checklist ticks.
- [email](email.md): protocol review of MIME and outcome mapping (read-only).
- [security](security.md): review of SSRF, secret hygiene and header injection (read-only).

## Shared contracts

### Value objects (`Pidgey\Email\`)

- **`Message`** (final readonly):
  - `string $to`;
  - `string $subject`;
  - `?string $text`;
  - `?string $html`.

  At least one of `text` / `html` is non-null; otherwise the constructor throws
  `LogicException`.
- **`SendActor`** (final readonly): `static token(int $tokenId)` | `static user(int $userId)`.
  Its `logContext(): array` returns `['tokenId' => n]` or `['userId' => n]`.
- **`SendOutcome`** (final readonly):
  - `static sent(int $code, string $message)`;
  - `static failed(string $failureCode, ?string $step, ?int $replyCode, ?string $replyMessage, ?string $tlsReason)`;
  - `isSent(): bool` plus getters.

  `failureCode` is one of the seven transport codes or the three send-time codes
  (`SMTP_INSECURE_CREDENTIALS`, `SMTP_HOST_NOT_ALLOWED`, `SMTP_PASSWORD_UNREADABLE`, all with
  step `send-time-check`). `replyMessage` is already redacted and sanitized. `tlsReason` is
  for the log only and never goes into a response.

### `SendFlow`

- `send(SmtpConnection $connection, Message $message, SendActor $actor): SendOutcome`.
- Never throws for SMTP or send-time failures. Unexpected errors (DB, `LogicException`)
  propagate and become 500.
- Order of operations:
  1. credentials with `encryption: none` → `SMTP_INSECURE_CREDENTIALS`;
  2. `HostPolicy::check(host)` → `SMTP_HOST_NOT_ALLOWED` or `SMTP_CONNECTION_FAILED`
     (unresolvable);
  3. `PasswordCipher::decrypt()` (only when a password is stored), wrapped in `SecretString`
     at once; `DecryptionFailed` or `SodiumException` → `SMTP_PASSWORD_UNREADABLE`;
  4. `MimeBuilder::build()`;
  5. transport call with the checked IP, `host`, `port`, `encryption`, `SessionSettings`,
     credentials, envelope (`fromAddress`, `[to]`) and MIME;
  6. `wipe()` in `finally`;
  7. reply text → `ReplyText::clean(text, ?SecretString, ?username)`;
  8. one log line;
  9. return.

### `HostPolicy` (`Pidgey\Smtp\`)

- `HostResolver` interface: `resolve(string $host): list<string>` returns IP strings, or an
  empty list on failure.
- `SystemHostResolver` returns `gethostbynamel()` (A) merged with
  `dns_get_record(DNS_AAAA)['ipv6']`. An AAAA error is ignored.
- `HostPolicy(HostResolver $resolver, bool $allowPrivate)`.
- `check(string $host): HostCheck`:
  - an IP literal (bracket-free; `HostName` already rejects zone ids) is used without
    resolving;
  - `HostCheck` is `allowed(string $ip)` | `denied()` | `unresolvable()`;
  - IPv4 is preferred: the first IPv4, else the first IPv6;
  - every address is checked against the denied ranges listed in
    `send-flow.md#host-check`;
  - `::ffff:a.b.c.d` is checked as its IPv4 address.

### `ReplyText` (`Pidgey\Email\`)

- `clean(string $text, #[SensitiveParameter] ?SecretString $password, ?string $username): string`.
- Replaces every occurrence of the following with `[redacted]`:
  - the password;
  - `base64("\0".username."\0".password)`;
  - `base64(password)`.

  Then it sanitizes: bytes outside `0x20`–`0x7E` become `?`, tabs become spaces, runs of
  spaces collapse, the text is trimmed, and anything over 512 characters is cut to 509 plus
  `...`.

### Attempt log

- Monolog through `Container::logger()`, message `smtp-send-attempt`, `info` when sent and
  `warning` when failed.
- Context:
  - `organizationId` and `connectionId`;
  - `tokenId` or `userId`;
  - `recipient`;
  - `outcome` (`sent` or the failure code), `step`, `replyCode`, `reply` (sanitized),
    `tlsReason`.
- Absent fields are omitted. Never the subject, the body, the username or the password.

### `SmtpFailureException` (`Pidgey\Http\`)

- Extends `HttpException`.
- `static fromOutcome(SendOutcome $outcome, int $port, SmtpEncryption $encryption): self`
  produces exactly the status, code and message of `failures.md#fixed-messages`, including:
  - the `SMTP_AUTH_FAILED` variants, keyed on the step: `AUTH` → rejected credentials;
    `EHLO` / no-mechanism → no supported mechanism; `MAIL FROM` → requires authentication;
  - `SMTP_REJECTED` by step;
  - the port hints (465 with `tls` / `none` on a timeout; 587 / 25 with `ssl` on a TLS
    failure).
- `smtp` is `['code' => replyCode, 'message' => replyMessage]` only when the outcome has a
  reply and the code may carry one (never for `CONNECTION_FAILED`, `PROTOCOL_ERROR`,
  `TIMEOUT` or the 409s).
- Calling it with a sent outcome is a `LogicException`.

### Container

- `hostPolicy()`: `allowPrivate = Env::get('SMTP_ALLOW_PRIVATE_HOSTS') === 'true'`.
- `transport()` (from #173, if it does not add it itself).
- `sendFlow()`.

`SMTP_ALLOW_PRIVATE_HOSTS` never refuses boot (`configuration.md#rules`), so
`ConfigValidator` gets no check. It only gets read.

## CI Checks

- `source/`: `make tests` (CI job: `backend_tests`), `composer lint`.

## Notes

- The transport-level classification is #173's. Here, each failure code is reached once
  through `SendFlow` to prove the mapping end to end.
- No HTTP handler, rate limit or `NO_SMTP_CONNECTION` here (#166 and the `/v1/messages`
  issue).
