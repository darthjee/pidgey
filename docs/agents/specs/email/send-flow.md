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
   (see [message-scope.md](message-scope.md#message-format)), then a best-effort `QUIT`.
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
2. [Host check](#host-check) → a denied address is `409 SMTP_HOST_NOT_ALLOWED`. This check
   also runs before the password is decrypted.
3. Decrypt the stored password → any failure is `409 SMTP_PASSWORD_UNREADABLE` (see
   [password-encryption.md](password-encryption.md#read-path)).

### Host check

Protects against server-side request forgery and DNS rebinding (see
[smtp-connections.md](smtp-connections.md#host-policy)):

- The `host` is **resolved once** (A and AAAA records). An IP literal is used as is, without
  resolution.
- Resolver rule: A records come from the system resolver (so the hosts file is honored);
  AAAA records come from a DNS query. An error in the AAAA lookup is treated as "no AAAA
  records", never as a failure. The result is the A addresses followed by the AAAA
  addresses; an empty result is a resolution failure.
- A resolution failure (no address, DNS error) keeps today's outcome:
  `SMTP_CONNECTION_FAILED`.
- When `SMTP_ALLOW_PRIVATE_HOSTS` is `false` (the default, see
  [architecture/configuration.md](../architecture/configuration.md#smtp-transport)), every
  resolved address is checked; if **any** of them is in a denied range, the send is refused
  with `409 SMTP_HOST_NOT_ALLOWED` —
  `The SMTP host resolves to an address that is not allowed`, no `error.smtp`, and the server
  is never contacted.
- Denied ranges (IPv4 and IPv6):
  - loopback (`127.0.0.0/8`, `::1`);
  - private networks: RFC 1918 (`10.0.0.0/8`, `172.16.0.0/12`, `192.168.0.0/16`) and unique
    local `fc00::/7`;
  - link-local: `169.254.0.0/16` (including the cloud metadata address `169.254.169.254`)
    and `fe80::/10`;
  - shared address space `100.64.0.0/10`;
  - unspecified (`0.0.0.0/8`, `::`);
  - multicast (`224.0.0.0/4`, `ff00::/8`);
  - reserved ranges (e.g. `240.0.0.0/4`, `255.255.255.255`);
  - an IPv4-mapped IPv6 address (`::ffff:a.b.c.d`) is checked as its IPv4 address.
- When `SMTP_ALLOW_PRIVATE_HOSTS` is `true` (the development and test env files), no
  address is denied.
- The transport then connects to the **checked IP**, never re-resolving `host` (so a DNS
  answer cannot change between the check and the connect): an IPv4 address is preferred;
  with several addresses one is picked, with **no fallback** to the other addresses in v1.
- The TLS peer name and SNI stay the connection's `host` (the
  [TLS policy](smtp-connections.md#tls-policy) and its IP-literal rules are unchanged).
- Ports are not restricted.

**Pending decision — host check gaps** (security follow-ups, not decided):

- IPv6 forms that embed an IPv4 address are not checked as their IPv4 address: NAT64
  `64:ff9b::/96` and `64:ff9b:1::/48` (medium risk on DNS64/NAT64 networks, where a name
  can reach an internal IPv4 address through the translator), IPv4-compatible `::/96` and
  6to4 `2002::/16`.
- Some reserved IPv4 ranges are not denied: `192.0.0.0/24` (IETF protocol assignments),
  `198.18.0.0/15` (benchmarking) and the TEST-NETs (`192.0.2.0/24`, `198.51.100.0/24`,
  `203.0.113.0/24`).

## SMTP session

One command, then its reply, then the next command (no `PIPELINING`). Every reply is read
and classified per [Replies](#replies); the failure codes and fixed messages are in
[failures.md](failures.md).

### Connect and greeting

- `none` and `tls`: open a plain TCP connection to the checked IP and `port` (see
  [Host check](#host-check)). `ssl`: open it and run the TLS handshake immediately
  (implicit TLS, [TLS policy](smtp-connections.md#tls-policy)); the peer name is `host`.
- TCP connect failure (DNS, refused, unreachable, timeout before TCP is established) →
  `SMTP_CONNECTION_FAILED`.
- `ssl` handshake or certificate failure → `SMTP_TLS_FAILED`, connection closed without
  `QUIT`.
- Wait for the greeting. Connecting, the `ssl` handshake and the greeting are bounded by
  the **connect timeout** (see [Timeouts](#timeouts)); a greeting timeout is
  `SMTP_TIMEOUT` (TCP succeeded).
- The peer closing the connection before sending any greeting byte →
  `SMTP_CONNECTION_FAILED` (see [Replies](#replies)).
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
- The `EHLO` reply: the first line is always the server's greeting name, never an
  extension; each later line is one extension (case-insensitive keyword plus parameters),
  read for `STARTTLS`, `AUTH`, `SMTPUTF8` and `SIZE`.
- `AUTH` mechanisms are read from both `AUTH PLAIN LOGIN` and the legacy `AUTH=LOGIN PLAIN`
  form; several `AUTH` lines merge into the union of their mechanisms (case-insensitive,
  compared in upper case).
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
  [message-scope.md](message-scope.md#addresses)). Rejected → `SMTP_REJECTED` ("rejected
  the sender"), 4xx → `SMTP_TEMPORARY_FAILURE`, `530` → see [`AUTH`](#auth).
- `RCPT TO:<recipient>`. Rejected → `SMTP_REJECTED` ("rejected the recipient").
- `DATA`, expecting `354`; then the MIME message with **dot-stuffing** (RFC 5321 §4.5.2: a
  line starting with `.` gets an extra `.`; done by the transport, not the MIME builder),
  ending with `CRLF.CRLF`. A rejection of `DATA` or of the final `.` → `SMTP_REJECTED`
  ("rejected the message"); an oversized message is rejected by the server (`552`).
  The transport splits the message on CRLF, bare CR or bare LF and rewrites every line with
  CRLF before dot-stuffing, so a bare CR or LF never reaches the wire (SMTP smuggling).
- **Success is the reply to the final `.`** (2xx); it feeds the success `smtp` field.
- `QUIT` is best-effort: its reply or failure never turns a sent message into a failure.

### Replies

Parsing follows RFC 5321 §4.2:

- Lines are `NNN-text` (continuation) and `NNN text` / `NNN` (last line); read until the
  last line. Every line of one reply must carry the same code. `NNN` without text and
  `NNN-` with an empty text are valid.
- CRLF is expected; a bare LF is tolerated on read (a trailing CR is stripped). Pidgey
  always writes CRLF.
- Limits: at most 100 lines per reply, at most 2048 bytes per line. The line limit counts
  the content **without** the CRLF: no LF within 2048 + 2 bytes is a limit exceeded.
- Raw bytes (NUL, a stray CR inside the text, non-ASCII) pass through the parser untouched;
  sanitization happens later (see [failures.md](failures.md#sanitization-and-length-cap)).

**Malformed reply** → `502 SMTP_PROTOCOL_ERROR`, no `error.smtp`:

- a line not starting with three digits followed by `-`, a space or the end of the line
  (an empty line, a bare CRLF, is malformed);
- a code outside 200–599;
- mixed codes within one reply;
- a limit exceeded;
- the peer closing the connection mid-reply.

A close **between** replies (e.g. `421` then close) is not malformed: the received reply is
used normally.

A close **with no reply at all** (the peer closes before sending any byte of the expected
reply) is not malformed either; the stream reports it as a distinct "closed" failure,
classified by step (see [failures.md](failures.md#classification-rules)):

- at the greeting → `SMTP_CONNECTION_FAILED` (servers that block a client often accept the
  TCP connection, then drop it);
- at any later step → `SMTP_PROTOCOL_ERROR`;
- during `QUIT` → ignored (`QUIT` is best-effort).

#### Reply text

Used for `error.smtp.message` and for the success `smtp.message`:

1. Drop the code and separator of every line and join the texts with one space.
2. If every line starts with the same enhanced status code `X.Y.Z` (e.g. `5.1.1`) whose
   `X` equals the reply code's first digit, keep it once, at the front. Otherwise the text
   is kept as is.
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
  reply to the final `.`. It bounds the **whole reply**, not each line: one budget
  `min(command timeout, time remaining)` is computed per reply and each line read gets what
  is left of it, so a server sending one line just under the timeout cannot stretch a reply
  (slowloris). Each write (looping until every byte is written) is bounded the same way.
- A wait whose remaining time is zero or less fails immediately as a timeout, without any
  I/O.
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

One log line per attempt, via monolog, with the message `smtp-send-attempt`: level `info`
when the message was sent, `warning` when the attempt failed (send-time checks included).

| Context field | Carries |
| --- | --- |
| `organizationId` | The connection's organization. |
| `connectionId` | The connection used. |
| `tokenId` / `userId` | The acting API token (`POST /v1/messages`) or the acting user (the test email); exactly one of them. |
| `recipient` | The recipient address. |
| `outcome` | `sent`, or the failure code (`SMTP_*`). |
| `step` | The failing step, one of the [step values](#step-values). |
| `replyCode` | The SMTP reply code, when a reply was received. |
| `reply` | The reply text, redacted and sanitized ([failures.md](failures.md#sanitization-and-length-cap)), when a reply was received. |
| `tlsReason` | The OpenSSL reason of a TLS failure (log only, never in a response). |

### Step values

The `step` field is a contract; its values are:

| Value | Failing step |
| --- | --- |
| `connect` | TCP connect (including resolution failures). |
| `greeting` | The server's `220` greeting. |
| `ehlo` | `EHLO` (before or after `STARTTLS`). |
| `starttls` | `STARTTLS` and the TLS handshake. |
| `auth` | `AUTH` and its `334` steps, including "no supported mechanism" (credentials never sent, no reply). |
| `mail-from` | `MAIL FROM` (including `530` without credentials). |
| `rcpt-to` | `RCPT TO`. |
| `data` | `DATA` (the `354` intermediate reply). |
| `end-of-data` | The reply after the message content (`.`). |
| `send-time-check` | `SMTP_INSECURE_CREDENTIALS`, `SMTP_HOST_NOT_ALLOWED` and `SMTP_PASSWORD_UNREADABLE`; the server is never contacted. |

Absent fields are omitted. Never the body, the subject, the username or the password.
There is **no protocol trace in v1**; a future debug trace must redact `AUTH` lines and
`DATA` content.

## Secret hygiene

Secrets are the decrypted password, the `AUTH` payload (base64 of `\0user\0pass`, the
`LOGIN` password line) and the encryption key. The username is not secret, but is not
logged needlessly. No secret may appear in a response, a log record (message, context,
exception trace) or an exception message:

1. Every parameter carrying a secret (decrypt, transport credentials, the stream write of
   an `AUTH` line, …) is marked `#[SensitiveParameter]`.
2. The password travels in the value object `Pidgey\Infra\SecretString` (see
   [architecture/backend.md](../architecture/backend.md#logging)):
   - it implements **neither** `__toString` nor `JsonSerializable`: an accidental
     `(string)` cast throws instead of silently sending a placeholder as the password, and a
     log context renders it as its class name only;
   - `__debugInfo` returns `[redacted]` (`print_r`, `var_dump`); `__serialize`,
     `__unserialize` and `__clone` throw;
   - `reveal()` is the only accessor, called where the `AUTH` line is built;
   - `wipe()` overwrites the value with `sodium_memzero()`; afterwards `reveal()` throws.

   `var_export` can still show private properties: an accepted, documented gap, since the
   application never uses it. The password decryption returns a plain string, wrapped into a
   `SecretString` immediately by the send flow.
3. Exception messages are built only from fixed text, the step name and the server's reply
   — never from a line Pidgey sent.
4. Echo redaction of reply texts (see [failures.md](failures.md#echo-redaction)).
5. Best effort: `SecretString::wipe()` (`sodium_memzero()`) on the decrypted password after
   the session (PHP may hold copies; defense in depth, not a guarantee).
6. Outside `development` / `test`, the boot sets `zend.exception_ignore_args` through
   `Pidgey\Infra\Runtime::harden()` (see
   [architecture/configuration.md](../architecture/configuration.md#smtp-transport)), so no
   stack trace carries arguments.

Tests run every failure path and the success path with a canary password; neither the
password nor its base64 forms (alone or inside the `AUTH` payload) may appear in the
response body, any log record or any exception message.
