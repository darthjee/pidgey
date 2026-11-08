# Synchronous Send Flow

Purpose: define, step by step, what happens when a send request is received, down to each
SMTP command and its success and failure mapping.

Sending is **synchronous**: the HTTP request blocks until the SMTP transaction ends
and the response carries the outcome. There is no queue and no retry in v1.

1. `POST /v1/messages` arrives with a Bearer token (see [api](../api.md)).
2. Authenticate the token; require scope `send`; resolve the token's organization.
3. Validate the payload (see [message-scope.md](message-scope.md)); invalid → `400 VALIDATION_FAILED`.
4. Resolve the organization's **active** SMTP connection; none → `409 NO_SMTP_CONNECTION`.
5. Run the [send-time checks](#send-time-checks), then open the session: connect (with
   `ssl` implicit TLS, or plain then `STARTTLS` for `tls`), greeting, `EHLO`, authenticate
   if credentials exist (see [SMTP session](#smtp-session)).
6. `MAIL FROM` (connection `from_address`), `RCPT TO`, `DATA` with the built MIME message
   (see [message-scope.md](message-scope.md#mime-message)), then a best-effort `QUIT`.
7. Return the outcome: success with the SMTP server's reply to the final `.`, or a
   categorized failure (see [failures.md](failures.md)).
8. Log the attempt (see [Attempt log](#attempt-log)).

The test email (`POST /smtp-connections/:id/test.json`) runs steps 5–8 with its fixed
content; the rules below apply to both alike.

**Pending decision:** whether send attempts are persisted (a `messages` log table).

## Send-time checks

Run in this order, before any connection is opened; on failure the server is never
contacted and no `error.smtp` is returned:

1. Credentials (`username` and `password` set) with `encryption: none` →
   `409 SMTP_INSECURE_CREDENTIALS`. A password is never sent in clear text. This check
   runs before the password is decrypted.
2. Decrypt the stored password → any failure is `409 SMTP_PASSWORD_UNREADABLE` (see
   [password-encryption.md](password-encryption.md#read-path)).

## SMTP session

One command, then its reply, then the next command (no `PIPELINING`). Every reply is read
and classified per [Replies](#replies); the failure codes and fixed messages are in
[failures.md](failures.md).

### Connect and greeting

- `none` and `tls`: open a plain TCP connection to `host:port`. `ssl`: open it and run the
  TLS handshake immediately (implicit TLS, [TLS policy](smtp-connections.md#tls-policy)).
- TCP connect failure (DNS, refused, unreachable, timeout before TCP is established) →
  `SMTP_CONNECTION_FAILED`.
- `ssl` handshake or certificate failure → `SMTP_TLS_FAILED`, connection closed without
  `QUIT`.
- Wait for the greeting. Connecting, the `ssl` handshake and the greeting are bounded by
  the **connect timeout** (see [Timeouts](#timeouts)); a greeting timeout is
  `SMTP_TIMEOUT` (TCP succeeded).
- Greeting `220` → continue. `554` → `SMTP_REJECTED` (rejected the connection); `421` or
  any other 4xx → `SMTP_TEMPORARY_FAILURE`; both with `error.smtp`.

### `EHLO`

- Send `EHLO <client name>`. The argument is the **client's** name (RFC 5321 §4.1.4),
  never the connection's `host`:
  - `SMTP_EHLO_NAME` when set (see
    [architecture/configuration.md](../architecture/configuration.md#smtp-transport));
  - otherwise the address literal of the socket's local address after connecting, e.g.
    `[203.0.113.7]` or `[IPv6:2001:db8::7]`.
  - Never `gethostname()`, the host of `FRONTEND_BASE_URL`, or the connection's `host`.
- The `EHLO` reply: the first line is the server's greeting name; each later line is one
  extension (case-insensitive keyword plus parameters), read for `STARTTLS`, `AUTH`,
  `SMTPUTF8` and `SIZE`.
- `EHLO` rejected → classified as any other reply (5xx `SMTP_REJECTED` "rejected the
  connection", 4xx `SMTP_TEMPORARY_FAILURE`). **No `HELO` fallback** (`HELO` cannot
  negotiate TLS or `AUTH`).
- The same name is used for the second `EHLO` after `STARTTLS`.

### `STARTTLS` (`encryption: tls`)

Never fall back to plain text on a `tls` connection (defense against STARTTLS stripping).
There is no per-connection "opportunistic TLS" option; plain text is only
`encryption: none`.

| Situation | Outcome | `error.smtp` |
| --- | --- | --- |
| The `EHLO` reply does not advertise `STARTTLS` | `SMTP_TLS_FAILED`; best-effort `QUIT` | absent (the `250` was not a failure) |
| `STARTTLS` answered with anything but `220` (`454`, `5xx`, …) | `SMTP_TLS_FAILED` (not `SMTP_TEMPORARY_FAILURE` / `SMTP_REJECTED`) | the reply |
| `220`, then the handshake or the certificate check fails | `SMTP_TLS_FAILED`; connection closed without `QUIT` | absent |
| Any bytes received after `220` and before the handshake | `SMTP_TLS_FAILED`; session aborted (STARTTLS command-injection class) | absent |

- No `AUTH` and no `MAIL FROM` before a successful `STARTTLS`.
- The handshake follows the [TLS policy](smtp-connections.md#tls-policy) and is bounded by
  the command timeout.
- After the upgrade, send `EHLO` again and **discard the pre-TLS extension list**
  (RFC 3207); the `AUTH` mechanisms are read from the post-TLS `EHLO` only.

### `AUTH`

Credentials are present when `username` and `password` are set (always both or neither;
with `encryption: none` the [send-time checks](#send-time-checks) already refused them).

- Mechanisms are read from the last `EHLO` (post-TLS for `tls`). `PLAIN` is preferred,
  `LOGIN` is the fallback; any other mechanism is out of scope for v1 (see
  [smtp-connections.md](smtp-connections.md#authentication)).
- `PLAIN`: a single command with an initial response,
  `AUTH PLAIN <base64("\0" username "\0" password)>` (empty authzid).
- `LOGIN`: `AUTH LOGIN`, then answer the two `334` prompts in order — base64 username,
  then base64 password — without parsing the prompt text.
- Credentials are UTF-8 bytes, base64-encoded.

| Situation | Outcome | `error.smtp` |
| --- | --- | --- |
| Credentials set; the final `EHLO` advertises no `AUTH` | `SMTP_AUTH_FAILED`, credentials never sent, `QUIT` | absent |
| Credentials set; `AUTH` advertised, but neither `PLAIN` nor `LOGIN` | same | absent |
| `235` | authenticated; continue | — |
| Any other reply to `AUTH` or to a `334` step (`535`, `534`, `538`, `454`, …) | `SMTP_AUTH_FAILED` (the step wins over the reply class) | the reply |
| No credentials; the server advertises `AUTH` | no authentication; continue | — |
| No credentials; `MAIL FROM` answered `530` | `SMTP_AUTH_FAILED`, not `SMTP_REJECTED` | the reply |

- A credential rejection on `PLAIN` is never retried with `LOGIN`.

### Envelope and `DATA`

- `MAIL FROM:<from_address>` with **no `SIZE`** and no `BODY=8BITMIME` parameter; Pidgey
  never sends `SMTPUTF8` (addresses are ASCII-only, see
  [message-scope.md](message-scope.md#mime-message)). Rejected → `SMTP_REJECTED` ("rejected
  the sender"), 4xx → `SMTP_TEMPORARY_FAILURE`, `530` → see [`AUTH`](#auth).
- `RCPT TO:<recipient>`. Rejected → `SMTP_REJECTED` ("rejected the recipient").
- `DATA`, expecting `354`; then the MIME message with **dot-stuffing** (RFC 5321 §4.5.2: a
  line starting with `.` gets an extra `.`; done by the transport, not the MIME builder),
  ending with `CRLF.CRLF`. A rejection of `DATA` or of the final `.` → `SMTP_REJECTED`
  ("rejected the message"); an oversized message is rejected by the server (`552`).
- **Success is the reply to the final `.`** (2xx); it feeds the success `smtp` field.
- `QUIT` is best-effort: its reply or failure never turns a sent message into a failure.

### Replies

Parsing follows RFC 5321 §4.2:

- Lines are `NNN-text` (continuation) and `NNN text` / `NNN` (last line); read until the
  last line. Every line of one reply must carry the same code.
- CRLF is expected; a bare LF is tolerated on read (a trailing CR is stripped). Pidgey
  always writes CRLF.
- Limits: at most 100 lines per reply, at most 2048 bytes per line.

**Malformed reply** → `502 SMTP_PROTOCOL_ERROR`, no `error.smtp`:

- a line not starting with three digits followed by `-`, a space or the end of the line;
- a code outside 200–599;
- mixed codes within one reply;
- a limit exceeded;
- the peer closing the connection mid-reply.

A close **between** replies (e.g. `421` then close) is not malformed: the received reply is
used normally.

#### Reply text

Used for `error.smtp.message` and for the success `smtp.message`:

1. Drop the code and separator of every line and join the texts with one space.
2. If every line starts with the same enhanced status code (e.g. `5.1.1`), keep it once,
   at the front.
3. Then redact and sanitize (see [failures.md](failures.md#echo-redaction)).

Example: the three-line Gmail `550-5.1.1 …` reply becomes
`5.1.1 The email account that you tried to reach does not exist. Please try double-checking … - gsmtp`.

### Port / encryption mismatch

The port / encryption pair is never validated; a mismatch fails naturally and fast:

| Mismatch | On the wire | Outcome |
| --- | --- | --- |
| `ssl` on a plain-text port (587, 25) | the clear-text `220` banner is read as a broken TLS record | `SMTP_TLS_FAILED`, immediately |
| `tls` / `none` on 465 (implicit TLS) | both sides wait silently (banner vs. ClientHello) | `SMTP_TIMEOUT`, bounded by the connect timeout |

On the well-known pairs (port 465 with `tls` / `none`, `ssl` on port 587 or 25) the fixed
message carries a hint (see [failures.md](failures.md#port--encryption-hints)). Accepted
ambiguity: `none` without credentials on 587 may get
`530 5.7.0 Must issue a STARTTLS command first` at `MAIL FROM`, which is
`SMTP_AUTH_FAILED` with the server's text in `error.smtp`.

## Timeouts

PHP `max_execution_time` does not bound socket waits on Linux (CPU time only) and PHP
cannot read the web server / proxy timeout, so Pidgey enforces its own budget, configured
by `SMTP_CONNECT_TIMEOUT`, `SMTP_COMMAND_TIMEOUT` and `SMTP_TOTAL_TIMEOUT` (see
[architecture/configuration.md](../architecture/configuration.md#smtp-transport)):

- **Connect timeout:** TCP connect, the `ssl` handshake and the `220` greeting.
- **Command timeout:** each command's reply, including the `STARTTLS` handshake and the
  reply to the final `.`.
- **Session deadline:** fixed at connect time from `SMTP_TOTAL_TIMEOUT` (using
  `Pidgey\Infra\Clock`); every operation waits `min(its own timeout, time remaining)`.
- No separate `DATA` timeout (RFC 5321's 10 minutes cannot fit a synchronous request).
  Trade-off: a slow server may accept the message after Pidgey gave up, so an
  `SMTP_TIMEOUT` after the final `.` does not prove the message was not delivered.
- Timeout → code: before TCP is established → `SMTP_CONNECTION_FAILED`; anything after
  (handshake, greeting, any reply, the session deadline) → `SMTP_TIMEOUT`.
- Known gap, accepted: DNS resolution (`getaddrinfo`) blocks and is not bounded by the
  connect timeout.
- `SMTP_TOTAL_TIMEOUT` must stay below the web server / proxy request timeout (operator
  rule, see the deployment docs).

## Attempt log

One log line per attempt, via monolog: organization, connection id, token id (or the
acting user for the test email), recipient, outcome (code, failing step, reply code,
sanitized reply, and the OpenSSL reason for TLS failures). Never the body, the subject or
credentials. There is **no protocol trace in v1**; a future debug trace must redact `AUTH`
lines and `DATA` content.

## Secret hygiene

Secrets are the decrypted password, the `AUTH` payload (base64 of `\0user\0pass`, the
`LOGIN` password line) and the encryption key. The username is not secret, but is not
logged needlessly. No secret may appear in a response, a log record (message, context,
exception trace) or an exception message:

1. Every parameter carrying a secret (decrypt, transport credentials, the stream write of
   an `AUTH` line, …) is marked `#[SensitiveParameter]`.
2. The password travels in a value object (e.g. `Pidgey\Email\SecretString`) whose
   `__toString`, `__debugInfo`, `__serialize` and `jsonSerialize` return `[redacted]`; the
   value is only obtained through one explicit method (e.g. `reveal()`), called where the
   `AUTH` line is built.
3. Exception messages are built only from fixed text, the step name and the server's reply
   — never from a line Pidgey sent.
4. Echo redaction of reply texts (see [failures.md](failures.md#echo-redaction)).
5. Best effort: `sodium_memzero()` on the decrypted password after the session (PHP may hold
   copies; defense in depth, not a guarantee).
6. Outside `development` / `test`, the boot sets `zend.exception_ignore_args` (see
   [architecture/configuration.md](../architecture/configuration.md#smtp-transport)), so no
   stack trace carries arguments.

Tests run every failure path and the success path with a canary password; neither the
password nor its base64 forms (alone or inside the `AUTH` payload) may appear in the
response body, any log record or any exception message.
