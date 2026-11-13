# Implement Email: SMTP Transport and Send Flow

Purpose: map the SMTP transport and the synchronous send flow to their implementation work
and define the contracts shared by several implementation issues; the decisions owned by
#152 are settled in the permanent specs linked below.

> Temporary — part of epic #149. Permanent specs win on conflict; update them, not this
> file, when a decision changes the target behavior.

Index: [implement-email.md](../implement-email.md).

## Open items

All settled by #152.

- [x] **`tls` without `STARTTLS`:** `SMTP_TLS_FAILED`, never a downgrade; any non-`220`
  reply to `STARTTLS` is `SMTP_TLS_FAILED`; re-`EHLO` after the upgrade, pre-TLS extensions
  discarded; bytes between `220` and the handshake abort the session —
  [email/send-flow.md](../email/send-flow.md#starttls-encryption-tls).
- [x] **`AUTH` mechanisms:** `PLAIN` (initial response) then `LOGIN`, read from the last
  `EHLO`; no supported mechanism or no `AUTH` while credentials exist →
  `SMTP_AUTH_FAILED` without sending credentials; `530` at `MAIL FROM` without credentials →
  `SMTP_AUTH_FAILED`; credentials require `tls` / `ssl` (`400` on create / update,
  `409 SMTP_INSECURE_CREDENTIALS` at send time) —
  [email/send-flow.md](../email/send-flow.md#auth),
  [email/smtp-connections.md](../email/smtp-connections.md#authentication),
  [api/endpoints/pidgey-admin/smtp-connections.md](../api/endpoints/pidgey-admin/smtp-connections.md#field-rules).
- [x] **Port / encryption mismatch:** no validation; `ssl` on a plain port fails fast with
  `SMTP_TLS_FAILED`, `tls` / `none` on 465 ends in `SMTP_TIMEOUT` bounded by the connect
  timeout; a fixed hint on well-known pairs —
  [email/send-flow.md](../email/send-flow.md#port--encryption-mismatch),
  [email/failures.md](../email/failures.md#port--encryption-hints).
- [x] **Multi-line replies:** RFC 5321 parsing with limits (100 lines, 2048 bytes per line),
  malformed replies → `502 SMTP_PROTOCOL_ERROR`; reply text = every line's text joined, the
  shared enhanced status code kept once —
  [email/send-flow.md](../email/send-flow.md#replies).
- [x] **Timeouts:** `SMTP_CONNECT_TIMEOUT` (10), `SMTP_COMMAND_TIMEOUT` (10),
  `SMTP_TOTAL_TIMEOUT` (25) with a session deadline; no separate `DATA` timeout; timeout →
  code rule — [email/send-flow.md](../email/send-flow.md#timeouts),
  [architecture/configuration.md](../architecture/configuration.md#smtp-transport).
- [x] **Timeout keys inconsistency:** the keys now exist in
  [architecture/configuration.md](../architecture/configuration.md#smtp-transport).
- [x] **IDN and non-ASCII input:** `host` is ASCII (LDH or IP literal, IDN in punycode, no
  conversion); addresses are ASCII-only, never `SMTPUTF8` —
  [api/endpoints/pidgey-admin/smtp-connections.md](../api/endpoints/pidgey-admin/smtp-connections.md#field-rules),
  [email/message-scope.md](../email/message-scope.md#addresses).
- [x] **TLS certificate verification:** always verify, peer name = `host` (IP literal via an
  IP SAN, no SNI), minimum TLS 1.2, system store or `SMTP_CA_FILE` (replaces it) —
  [email/smtp-connections.md](../email/smtp-connections.md#tls-policy).
- [x] **`failures.md` pending items:** sanitization (printable ASCII, cap 512 with `...`),
  every fixed `error.message`, the HTTP mapping (no `Retry-After`) —
  [email/failures.md](../email/failures.md#fixed-messages),
  [api/conventions/errors.md](../api/conventions/errors.md#smtp-send-failures).
- [x] **Secret hygiene:** `#[SensitiveParameter]`, a redacting password value object,
  exception messages without sent lines, no protocol trace, echo redaction,
  `sodium_memzero()`, `zend.exception_ignore_args` outside development / test —
  [email/send-flow.md](../email/send-flow.md#secret-hygiene),
  [email/failures.md](../email/failures.md#echo-redaction),
  [architecture/configuration.md](../architecture/configuration.md#smtp-transport).
- [x] **Hand-written transport vs. a library:** hand-written over PHP streams, MIME builder
  too; no new runtime dependency —
  [architecture/backend.md](../architecture/backend.md#smtp-transport).
- [x] **Namespace and layer:** `Pidgey\Email\` (send flow, feature) → `Pidgey\Smtp\`
  (protocol); no `Pidgey\Service\` namespace —
  [architecture/backend.md](../architecture/backend.md#layers).
- [x] **`EHLO` argument:** `SMTP_EHLO_NAME`, else the local address literal; never `host` —
  [email/send-flow.md](../email/send-flow.md#ehlo),
  [architecture/configuration.md](../architecture/configuration.md#smtp-transport).
- [x] **MIME headers and body:** `Date`, `From`, `To`, `Subject` (RFC 2047), `Message-ID`,
  `MIME-Version`, quoted-printable bodies, `multipart/alternative`; dot-stuffing in the
  transport; no `SIZE` — [email/message-scope.md](../email/message-scope.md#message-format),
  [email/send-flow.md](../email/send-flow.md#envelope-and-data).

## Building block: SMTP transport

- **Responsibility:** runs one SMTP session against a connection: open the stream (`ssl`
  implicit TLS, `tls` plain then `STARTTLS`, `none` plain), greeting, `EHLO`, `AUTH` when
  credentials exist, `MAIL FROM` / `RCPT TO` / `DATA`, best-effort `QUIT`, and returns the
  reply to the final `.` or a classified failure. It knows nothing about HTTP,
  organizations, repositories or logging; it depends only on `Pidgey\Infra\` (the monotonic
  `Clock` and `SecretString`).
- **Names** (namespace `Pidgey\Smtp\`), all delivered:
  - by #163: `Pidgey\Smtp\SmtpStream` (stream abstraction) with its socket implementation
    `SocketStream`, `Reply`, `ReplyParser`, `Deadline`, `EhloExtensions`, `SessionSettings`,
    `TlsContext`, `StreamFailure` (with `StreamFailureKind`), `MalformedReply`, and
    `Pidgey\Infra\SecretString`;
  - by #173: `Pidgey\Smtp\Transport` (interface) and its implementation `SmtpTransport`,
    `SmtpFailure`, `FailureCode`, `SmtpStep`, `TlsMode`, `Target`, `Credentials`, `Envelope`
    and `SmtpResult`, wired through `Pidgey\Container::smtpTransport()`.
- **Collaborators:** `Pidgey\Infra\Clock` (session deadline), `Pidgey\Infra\SecretString`
  (the password). The connection settings (host, checked IP, port, encryption), the session
  settings (`Pidgey\Smtp\SessionSettings`: timeouts, CA file, `EHLO` name, built by
  `Pidgey\Container::smtpSessionSettings()`) and the credentials are passed in by the send
  flow; the transport does not read `Env` or the repository.
- **Governing specs:** [email/send-flow.md](../email/send-flow.md#smtp-session) (session,
  replies, timeouts, secret hygiene);
  [email/smtp-connections.md](../email/smtp-connections.md) (authentication, TLS policy);
  [email/failures.md](../email/failures.md);
  [architecture/backend.md](../architecture/backend.md#layers).

## Building block: send flow

- **Responsibility:** given a connection, a recipient and the message fields, runs the
  send-time checks (`SMTP_INSECURE_CREDENTIALS`, then the host check / `SMTP_HOST_NOT_ALLOWED`
  — resolve `host` once and check every address unless `SMTP_ALLOW_PRIVATE_HOSTS` is `true` —
  then decrypt / `SMTP_PASSWORD_UNREADABLE`), passes the **checked IP** to the transport as
  the connect target (with `host` kept as TLS peer name and SNI),
  builds the MIME message, runs the transport, maps the outcome to a success body or an
  `SMTP_*` failure outcome (with echo redaction and sanitization of the reply text), and
  writes the attempt log line (step 8 of
  [email/send-flow.md](../email/send-flow.md#attempt-log)). It does not resolve the active
  connection (that is `POST /v1/messages`, out of scope) nor load the connection (the caller
  does).
- **Outcome, not exceptions:** `SendFlow` returns a `Pidgey\Email\SendOutcome` (sent or
  failed) and never throws for SMTP or send-time failures; the HTTP mapping of a failed
  outcome is `Pidgey\Http\SmtpFailureException::fromOutcome()` (status, fixed message, port
  hint, `error.smtp`), called by the HTTP handlers.
- **Names**, all delivered by #165:
  - `Pidgey\Email\`: `SendFlow`, `Message`, `MimeBuilder`, `SendOutcome`, `SendActor` (the
    acting token or user, for the log), `ReplyText` (echo redaction and sanitization);
  - `Pidgey\Smtp\`: `HostPolicy` (resolve once, denied ranges, IPv4 preferred) with the
    `HostResolver` interface, its `SystemHostResolver` implementation and the `HostCheck`
    result;
  - `Pidgey\Http\SmtpFailureException` carrying `error.smtp`;
  - wired through `Pidgey\Container::hostPolicy()` and `Pidgey\Container::sendFlow()`.

  The password value object is `Pidgey\Infra\SecretString` (delivered by #163;
  `PasswordCipher::decrypt()` keeps returning a `string`, which the send flow wraps into a
  `SecretString` immediately).
- **Collaborators:** the SMTP transport (above), the password encryption service
  ([encryption.md](encryption.md)), `Pidgey\Infra\WallClock` (`Date` header), `Pidgey\Infra\Env`
  (the [SMTP transport settings](../architecture/configuration.md#smtp-transport)), the
  application logger (monolog, see [endpoints.md](endpoints.md#building-block-operator-log)),
  `Pidgey\Http\HttpException` / `Pidgey\Http\ErrorResponse` / `Pidgey\Http\ErrorFormatter` (the
  error envelope).
- **Governing specs:** [email/send-flow.md](../email/send-flow.md) (steps 5–8,
  [host check](../email/send-flow.md#host-check));
  [email/smtp-connections.md](../email/smtp-connections.md#host-policy);
  [email/message-scope.md](../email/message-scope.md#message-format);
  [email/failures.md](../email/failures.md);
  [api/conventions/errors.md](../api/conventions/errors.md#smtp-send-failures).

## Implementation work

Names are **proposals** (see the lifecycle rules in [implement-email.md](../implement-email.md)).

1. [x] **Reply parser** — `Pidgey\Smtp\ReplyParser` / `Pidgey\Smtp\Reply` /
   `Pidgey\Smtp\EhloExtensions`: multi-line parsing, limits, malformed replies, the `EHLO`
   extension list, the joined reply text ([Replies](../email/send-flow.md#replies)).
2. [x] **Stream** — `Pidgey\Smtp\SmtpStream`, `Pidgey\Smtp\SocketStream` and
   `Pidgey\Smtp\TlsContext`: connect, implicit TLS, in-place `STARTTLS` upgrade with
   TLS 1.2+ and the verification policy (peer name, SNI rules, `SMTP_CA_FILE`), the local
   address, per-operation timeouts, the pending-bytes check
   ([TLS policy](../email/smtp-connections.md#tls-policy)).
3. [x] **Session deadline** — `Pidgey\Smtp\Deadline` on `Pidgey\Infra\Clock`: every wait is
   `min(its own timeout, time remaining)` ([Timeouts](../email/send-flow.md#timeouts)).
4. [x] **Transport** — `Pidgey\Smtp\Transport` / `Pidgey\Smtp\SmtpTransport`, with
   `SmtpFailure`, `FailureCode`, `SmtpStep`, `TlsMode`, `Target`, `Credentials`, `Envelope`,
   `SmtpResult` and `Pidgey\Container::smtpTransport()`: the step sequence, the
   no-downgrade and injection guard, `AUTH` `PLAIN` / `LOGIN`, line-ending normalization and
   dot-stuffing, best-effort `QUIT`, failure classification
   ([SMTP session](../email/send-flow.md#smtp-session),
   [Classification rules](../email/failures.md#classification-rules)).
5. [x] **MIME builder** — `Pidgey\Email\MimeBuilder`: headers, RFC 2047 subject,
   quoted-printable bodies, `multipart/alternative`
   ([Message format](../email/message-scope.md#message-format)).
6. [x] **Secret value object** — `Pidgey\Infra\SecretString` (`reveal()`, `wipe()` with
   `sodium_memzero()`, neither `__toString` nor `JsonSerializable`) and
   `#[SensitiveParameter]` on the stream's line write; the send flow (#165) marks its own
   secret-carrying parameters and wipes the password after the session
   ([Secret hygiene](../email/send-flow.md#secret-hygiene)).
7. [x] **Send flow** — `Pidgey\Email\SendFlow`: send-time checks (including the host check
   through `Pidgey\Smtp\HostPolicy`, [Host check](../email/send-flow.md#host-check)), echo redaction,
   sanitization (`Pidgey\Email\ReplyText`), the returned `Pidgey\Email\SendOutcome`, the
   attempt log line `smtp-send-attempt`
   ([Send-time checks](../email/send-flow.md#send-time-checks),
   [`error.smtp`](../email/failures.md#errorsmtp)).
8. [x] **HTTP exception** — `Pidgey\Http\SmtpFailureException::fromOutcome()`: failed outcome → status, fixed
   message (with the port hint), `error.smtp`
   ([Fixed messages](../email/failures.md#fixed-messages)).
9. **Validation changes** — the `host` LDH rule and the credentials-require-encryption rule
   on create / update (merged state)
   ([Field rules](../api/endpoints/pidgey-admin/smtp-connections.md#field-rules)); the
   `subject` control-character rule for `POST /v1/messages` later
   ([Validation](../email/message-scope.md#validation)).
10. [x] **Configuration** — `ConfigValidator` / `bin/check-config`: the five SMTP transport
    keys, their rules and failure names; `Pidgey\Smtp\SessionSettings` through
    `Container::smtpSessionSettings()`; `zend.exception_ignore_args` at web and CLI boot
    through `Pidgey\Infra\Runtime::harden()`
    ([SMTP transport](../architecture/configuration.md#smtp-transport)).
11. **Tests** — reply-by-reply unit tests against a fake stream, integration tests against
    Mailpit and the test CA, and the canary-password leak test (see
    [testing.md](testing.md)).
    Transport: reply-by-reply unit tests, Mailpit and `FakeSmtpServer` integration tests and
    the exception-message canary check are delivered by #173. The leak test through response
    bodies and log records, and the HTTP columns of the failure mapping, follow with #165.

## Shared contracts

Described by what each carries; signatures are left to `plan-issue`.

### Transport

| Direction | Carries |
| --- | --- |
| Input: connection settings | `host` (TLS peer name and SNI), the **checked IP** to connect to (see [Host check](../email/send-flow.md#host-check)), `port`, `encryption` (`none` / `tls` / `ssl`). |
| Input: session settings | connect, command and total timeouts; the CA file (or none); the `EHLO` name (or none: the local address literal). |
| Input: credentials | `username` and the **decrypted** password as a `SecretString`, or none. Never logged. |
| Input: envelope | the sender (`fromAddress`) and the recipient list (one address in v1). |
| Input: message | the complete MIME message (headers and body), not yet dot-stuffed. |
| Output | a **result** (success) or a **failure** (below). |

### Stream

The transport reads and writes through a stream abstraction, so a fake SMTP server plugs in
without a socket (unit tests) and a socket fixture or Mailpit plugs in for integration tests
(see [testing.md](testing.md)). It carries:

- **open** a target (the checked IP, port, the peer name `host`, implicit TLS or not, CA file) within a given timeout (the
  connect timeout, capped by the deadline);
- **local address** of the open socket (for the `EHLO` address literal);
- **write** a line (CRLF added, looping until every byte is written), **read** a line (up
  to 2048 bytes of content plus CRLF), each within a given deadline; the line write takes a
  `#[SensitiveParameter]`;
- **upgrade** to TLS in place (for `STARTTLS`), with the verification policy, within a given
  deadline; fails with "unexpected data" when bytes are pending before the handshake (the
  injection guard);
- **close** (idempotent);
- each operation either succeeds or reports a stream-level failure (`StreamFailure`, kind
  `StreamFailureKind`): connect failure (before TCP is established, including a connect
  timeout), TLS failure (with the OpenSSL reason, for the log only), timeout, connection
  closed by the peer before any byte, or unexpected data; its message is fixed text, never
  sent or received bytes;
- **per-reply budget:** the caller computes one deadline per reply,
  `min(command timeout, session time remaining)`, and passes it to every line read of that
  reply (see [Timeouts](../email/send-flow.md#timeouts)).

Delivered by #163 as `Pidgey\Smtp\SmtpStream` / `Pidgey\Smtp\SocketStream`, with the
`Pidgey\Tests\Support\FakeSmtpStream` fake ([email/testing.md](../email/testing.md#fake-smtp-server)).

### Result

| Field | Meaning |
| --- | --- |
| `code` | The SMTP reply code of the reply to the final `.` (e.g. `250`). |
| `message` | The reply text, redacted and sanitized ([failures.md](../email/failures.md#sanitization-and-length-cap)). |

It becomes the success body `{ "status": "sent", "smtp": { "code", "message" } }`.

The transport itself returns the **raw** `Reply` to the final `.` (`Pidgey\Smtp\SmtpResult`);
echo redaction, sanitization and the enhanced-status join that produce `message` are done by
the send flow (#165).

### Failure

| Field | Meaning |
| --- | --- |
| `failureCode` | One of `SMTP_CONNECTION_FAILED`, `SMTP_TLS_FAILED`, `SMTP_AUTH_FAILED`, `SMTP_REJECTED`, `SMTP_TEMPORARY_FAILURE`, `SMTP_PROTOCOL_ERROR`, `SMTP_TIMEOUT`. |
| `step` | The failing step (greeting, `EHLO`, `STARTTLS`, `AUTH`, `MAIL FROM`, `RCPT TO`, `DATA`, final `.`), which selects the `SMTP_REJECTED` / `SMTP_AUTH_FAILED` message. |
| `reply` | Optional: the server's reply to the failing command (`code`, text); absent when no reply was received (connect, TLS handshake, timeout, malformed reply, no supported mechanism). Never client-side data. |
| `reason` | Optional: the OpenSSL reason of a TLS failure, for the log line only. |

Mapping to HTTP, per [email/failures.md](../email/failures.md#fixed-messages). The send-time
codes `SMTP_INSECURE_CREDENTIALS`, `SMTP_HOST_NOT_ALLOWED` and `SMTP_PASSWORD_UNREADABLE`
(`409`, never `error.smtp`) come from the send flow, not the transport; a host that cannot be
resolved is `SMTP_CONNECTION_FAILED`.

| `failureCode` | HTTP status | `error.message` | `error.smtp` |
| --- | --- | --- | --- |
| `SMTP_CONNECTION_FAILED` | 502 | `Could not connect to the SMTP server` | never |
| `SMTP_TLS_FAILED` | 502 | `TLS negotiation with the SMTP server failed` (+ port hint) | the `STARTTLS` reply, if any |
| `SMTP_AUTH_FAILED` | 502 | by situation (rejected credentials / no supported mechanism / requires authentication) | the reply, if any |
| `SMTP_REJECTED` | 502 | by step (connection / sender / recipient / message) | the reply |
| `SMTP_TEMPORARY_FAILURE` | 503 | `The SMTP server is temporarily unavailable; try again later` | the reply |
| `SMTP_PROTOCOL_ERROR` | 502 | `The SMTP server sent an invalid reply` | never |
| `SMTP_TIMEOUT` | 504 | `The SMTP server timed out` (+ port hint) | never |

### New configuration keys

| Key | Carries | Status |
| --- | --- | --- |
| `SMTP_CONNECT_TIMEOUT` | Seconds for connect, `ssl` handshake and greeting (default `10`). | Decided, in [architecture/configuration.md](../architecture/configuration.md#smtp-transport). |
| `SMTP_COMMAND_TIMEOUT` | Seconds for each reply, `STARTTLS` handshake included (default `10`). | Decided, same section. |
| `SMTP_TOTAL_TIMEOUT` | Seconds for the whole session (default `25`). | Decided, same section. |
| `SMTP_CA_FILE` | Optional PEM bundle replacing the system CA store (test CA in test / CI). | Decided, same section. |
| `SMTP_EHLO_NAME` | Optional client name for `EHLO`. | Decided, same section. |
| `SMTP_ALLOW_PRIVATE_HOSTS` | Boolean (default `false`; `true` in the development and test env files): skip the denied ranges of the host check. | Decided by #153, same section. |
