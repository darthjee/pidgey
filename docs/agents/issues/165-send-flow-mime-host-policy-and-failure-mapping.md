# Issue: Send flow: MIME, host policy and failure mapping

## Description

Part of epic #149 (email integration). Implements the **send flow** on top of the SMTP
transport (#173): message building, the send-time checks (including the host policy), the
outcome mapping and the attempt log. It is the shared core that the test email (#166) and
`POST /v1/messages` call; it has no HTTP handler of its own.

Specs:

- `docs/agents/specs/email/send-flow.md` (send-time checks, host check, attempt log, secret
  hygiene);
- `docs/agents/specs/email/message-scope.md#message-format`;
- `docs/agents/specs/email/failures.md` (codes, fixed messages, `error.smtp`, echo redaction,
  sanitization);
- `docs/agents/specs/email/smtp-connections.md#host-policy`;
- `docs/agents/specs/api/conventions/errors.md#smtp-send-failures`;
- `docs/agents/specs/architecture/configuration.md#smtp-transport` (`SMTP_ALLOW_PRIVATE_HOSTS`);
- implementation notes: `implement-email/transport.md` (items 5, 7, 8; the send-flow building
  block, the Result and Failure contracts) and `implement-email/endpoints.md` (item 4, host
  policy; item 7, the `SMTP_ALLOW_PRIVATE_HOSTS` key).

## Problem

#163 delivered the stream and reply parsing, and #173 will deliver the SMTP session. Nothing
yet turns a stored connection plus a message into a send:

- no MIME message is built (headers, RFC 2047 subject, quoted-printable bodies,
  `multipart/alternative`);
- the send-time checks (`SMTP_INSECURE_CREDENTIALS`, `SMTP_HOST_NOT_ALLOWED`,
  `SMTP_PASSWORD_UNREADABLE`) do not exist, so a user-chosen `host` could reach internal
  addresses (SSRF, DNS rebinding);
- `SMTP_ALLOW_PRIVATE_HOSTS` is set in `.env`, compose and CI but never read or validated
  by `ConfigValidator`;
- transport failures have no mapping to HTTP status, fixed message and `error.smtp`, and
  reply texts are not redacted or sanitized;
- no attempt is logged.

## Expected Behavior

- Given a loaded connection, a recipient, the message fields and the actor, the send flow:
  1. refuses credentials with `encryption: none` (`SMTP_INSECURE_CREDENTIALS`) before
     decrypting;
  2. resolves `host` once, refuses any denied address unless `SMTP_ALLOW_PRIVATE_HOSTS` is
     `true` (`SMTP_HOST_NOT_ALLOWED`), treats a resolution failure as
     `SMTP_CONNECTION_FAILED`, and picks the checked IP (IPv4 preferred, no fallback);
  3. decrypts the password, wrapping it in `SecretString` immediately; any failure is
     `SMTP_PASSWORD_UNREADABLE`;
  4. builds the MIME message and runs the transport against the checked IP, keeping `host`
     as the TLS peer name and SNI;
  5. wipes the password after the session, whatever the outcome;
  6. redacts and sanitizes the reply text;
  7. returns a `SendOutcome`, either sent (`code`, `message`) or failed;
  8. writes exactly one attempt log line.
- The server is never contacted when a send-time check fails.
- `SmtpFailureException` turns a failed outcome into the exact status, fixed message
  (variants by step and situation, port hints) and `error.smtp` of `failures.md`.
- The canary password never appears in an outcome, a response body, a log record or an
  exception message, in plain or base64 form.

## Solution

### Scope

- **`Pidgey\Email\Message`** (`to`, `subject`, `text`, `html`) and
  **`Pidgey\Email\MimeBuilder`**:
  - headers in the spec's order;
  - `Date` from `Pidgey\Infra\WallClock` in UTC;
  - RFC 2047 subject (encoded-words of at most 75 characters, never splitting a UTF-8
    character, folded at 78);
  - `Message-ID` from `random_bytes(16)` at the `fromAddress` domain;
  - CRLF normalization, then quoted-printable for every part;
  - `multipart/alternative` with the boundary `=_pidgey_<32 hex>`.
- **`Pidgey\Smtp\HostPolicy`**: resolve once through an injectable resolver; check the denied
  ranges, IPv4 and IPv6, with IPv4-mapped addresses checked as IPv4; IP literals are not
  resolved; return the checked IP, preferring IPv4. `allowPrivate` is passed in as a `bool`:
  `Pidgey\Smtp\` never reads `Env`.
- **`SMTP_ALLOW_PRIVATE_HOSTS`**: added to `ConfigValidator` / `bin/check-config` (boolean,
  only exactly `true` is true), and to `Container` (`hostPolicy()`).
- **`Pidgey\Email\SendFlow`** and **`Pidgey\Email\SendOutcome`**:
  - the send-time checks in order;
  - the decrypt into `SecretString`;
  - `#[SensitiveParameter]` on every secret-carrying parameter;
  - `wipe()` in a `finally`;
  - echo redaction (the password and the base64 `AUTH` payload become `[redacted]`), then
    sanitization (printable ASCII, whitespace collapse, cap at 512 with `...`);
  - the attempt log line;
  - `Container::sendFlow()`.
- **`Pidgey\Http\SmtpFailureException`**: maps a failed outcome to the status, fixed
  message, port hint and `error.smtp`. It covers the three `409` send-time codes, which
  never carry `error.smtp`.
- **Tests:**
  - MIME builder unit tests (encoded-word boundaries, folding, QP line length, CRLF,
    multipart order);
  - host policy unit tests over every denied range, mapped addresses, literals, the allow
    flag and resolution failure, through the injectable resolver;
  - send flow tests on `FakeSmtpServer` and Mailpit;
  - the mapping unit tests;
  - the canary-password test on the success path and every failure path.

Not in scope:

- the transport session and its classification (#173);
- the HTTP handler and the rate limit (#166);
- resolving the active connection and `409 NO_SMTP_CONNECTION` (`POST /v1/messages`);
- the `subject` request validation (the `POST /v1/messages` issue);
- the system emails.

### Decisions

1. **Outcome, not exceptions:** `SendFlow` returns a `SendOutcome` (sent, or failed with
   code, step, optional reply and TLS reason, send-time codes included) and never throws an
   HTTP exception. HTTP callers (#166, `POST /v1/messages`) call
   `SmtpFailureException::fromOutcome()`; unauthenticated callers (password recovery,
   registration) log and answer their normal response.
2. **Resolver:** injectable `Pidgey\Smtp\HostResolver`; the default resolves A records with
   `gethostbynamel()` (honors `/etc/hosts`) and AAAA with `dns_get_record(DNS_AAAA)`; an
   AAAA lookup error counts as no AAAA records; no address at all is
   `SMTP_CONNECTION_FAILED`.
3. **Attempt log:** one line per attempt, message `smtp-send-attempt`, `info` when sent and
   `warning` when failed; context: `organizationId`, `connectionId`, `tokenId` or `userId`,
   `recipient`, and the outcome (`code`, `step`, `replyCode`, sanitized reply, TLS reason).
   The three send-time `409` failures are logged too, with step `send-time-check`. The actor
   (token or user) is passed to `SendFlow` as a small value object. Never the body, subject
   or credentials.
4. **Test depth:** every row of the mapping (variants and hints) asserted exactly in
   `SmtpFailureException` unit tests; each transport failure code reached at least once
   through `SendFlow` on `FakeSmtpServer` or Mailpit; transport classification depth stays
   in #173.
5. **Defense in depth:** `MimeBuilder` throws a `LogicException` (→ `500`) on control
   characters in the subject and on non-ASCII, CR or LF in an address, although validation
   upstream already rejects them.
6. **Spec fix:** `message-scope.md` names `Pidgey\Infra\Clock` (monotonic) for the `Date`
   header; it becomes `Pidgey\Infra\WallClock`, fixed by product-owner in this PR.

### Acceptance

- Every row of the `SMTP_*` mapping (including the variants and hints) is asserted exactly,
  and each transport failure code is reached at least once through `SendFlow` against the
  fake server or Mailpit.
- The three send-time codes are covered, each asserting that the server was never contacted.
- The matching items are ticked in the same PR:
  - `implement-email/transport.md` items 5, 7 and 8;
  - `implement-email/endpoints.md` item 4, and item 7's `SMTP_ALLOW_PRIVATE_HOSTS`.

### Dependencies

- #160 (`PasswordCipher`).
- #173 (`Transport`, `SmtpFailure`, `FailureCode`), which builds on #163. **#165 cannot
  start before #173 is merged.**

`PasswordCipher::decrypt()` returns a plain `string`: wrap it in `SecretString` at once
(decided in #163).

Agents: backend, email (review), security (review), product-owner (spec ticks and the
`message-scope.md` fix).

## Benefits

- One send path, already hardened (SSRF, credential leaks, header injection), shared by the
  test email and the public API.
- Users get stable, exact error messages with the server's reply when it helps them fix the
  connection.
