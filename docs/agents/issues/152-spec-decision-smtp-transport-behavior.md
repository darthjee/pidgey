# Issue: Spec decision: SMTP transport behavior

## Description

Settle the **SMTP transport behavior** for the email integration (epic #149) in the specs,
so the SMTP client and send flow can be implemented.

Depends on the implementation-spec issue of #149, which creates
`docs/agents/specs/implement-email/transport.md` listing these open items.

### Context

`docs/agents/specs/email/send-flow.md` (steps 5–8: connect, `ssl` implicit TLS or `tls`
STARTTLS, `EHLO`, `AUTH`, `MAIL FROM` / `RCPT TO` / `DATA`, `QUIT`, log) and
`docs/agents/specs/email/failures.md` (error codes `SMTP_CONNECTION_FAILED`,
`SMTP_TLS_FAILED`, `SMTP_AUTH_FAILED`, `SMTP_REJECTED`, `SMTP_TEMPORARY_FAILURE`,
`SMTP_TIMEOUT`, and `error.smtp`) define the flow. No transport code exists yet. The first
consumer is the test email (`POST /smtp-connections/:id/test.json`); `POST /v1/messages`
reuses it in a later epic.

## Problem

`email/send-flow.md`, `email/failures.md` and `implement-email/transport.md` leave the SMTP transport behavior undecided; these items had to be settled before the transport and the send flow can be implemented:

1. `encryption: tls` but the server does not advertise `STARTTLS` → `SMTP_TLS_FAILED`, never
   a downgrade to plain (confirm and specify).
2. **AUTH mechanisms** supported (PLAIN, LOGIN; order of preference) and the code when none
   is offered, or no AUTH is advertised while credentials exist (`SMTP_AUTH_FAILED` vs
   `SMTP_REJECTED`).
3. **Port/encryption mismatch** (`ssl` on 587, `none` on 465): expected outcome and how to
   avoid waiting for the full timeout where possible.
4. **Multi-line replies** (`250-…` / `250 …`): parsing and which text feeds
   `error.smtp.message`.
5. **Timeout budget**: configuration keys and defaults for connect and per-command timeouts,
   and how their total stays below PHP `max_execution_time` / the Apache timeout.
6. **Non-ASCII input**: IDN hostnames (punycode or reject) and non-ASCII `fromAddress` /
   recipient (SMTPUTF8 or reject).
7. **TLS certificate verification policy** (working assumption: always verify, peer name =
   `host`), including how a custom CA bundle is configured (needed by the test CA).
8. Pending items of `failures.md`: maximum `error.smtp.message` length after sanitization,
   the exact wording of the fixed messages, the HTTP status mapping.
9. **No secret leaks**: exceptions, debug output and conversation logs never include the
   `AUTH` payload or the decrypted password.

In addition, `implement-email/transport.md` assigns to #152: hand-written transport vs. a library, namespaces / layers, the `EHLO` argument and the MIME headers.

## Expected Behavior

The decisions below are recorded in the permanent specs.

### Decision 1 — `tls` without `STARTTLS` (no downgrade)

Never fall back to plain text on a `tls` connection (defense against STARTTLS stripping).
There is no per-connection "opportunistic TLS" option; plain text is only `encryption: none`.

| Situation (`tls` path) | Outcome | `error.smtp` |
| --- | --- | --- |
| The `EHLO` reply does not advertise `STARTTLS` | `SMTP_TLS_FAILED`; best-effort `QUIT` | absent (the `250` was not a failure) |
| `STARTTLS` answered with anything but `220` (`454`, `5xx`, …) | `SMTP_TLS_FAILED` (not `SMTP_TEMPORARY_FAILURE` / `SMTP_REJECTED`) | the reply |
| `220`, then the handshake or the certificate check fails | `SMTP_TLS_FAILED`; connection closed without `QUIT` | absent |
| `EHLO` rejected | no `HELO` fallback (`HELO` cannot negotiate TLS); code as for a rejected `EHLO` on any path | the reply |

- `error.message` is the fixed `SMTP_TLS_FAILED` string in every row.
- No `AUTH` and no `MAIL FROM` before a successful `STARTTLS`.
- After the upgrade, send `EHLO` again and discard the pre-TLS extension list (RFC 3207);
  the `AUTH` mechanisms are read from the post-TLS `EHLO` only.
- Any bytes the server sent after `220` and before the handshake abort the session with
  `SMTP_TLS_FAILED` (STARTTLS command-injection class).
- Applies to the test email and to `POST /v1/messages` alike.

### Decision 2 — `AUTH`

Credentials are present when `username` and `password` are set (they are always both set or
both empty).

#### Mechanisms

- `PLAIN` first, sent as an initial response (`AUTH PLAIN <base64("\0" user "\0" pass)>`,
  empty authzid); `LOGIN` as fallback (answer the two `334` prompts in order — username,
  then password — without parsing the prompt text).
- Credentials are UTF-8 bytes, base64-encoded.
- `CRAM-MD5`, `XOAUTH2`, `OAUTHBEARER` and any other mechanism are out of scope for v1.
- Mechanisms are read from the last `EHLO` (post-TLS for `tls`, see Decision 1).
- A credential rejection on `PLAIN` is never retried with `LOGIN`.

#### Mapping

| Situation | Outcome | `error.smtp` |
| --- | --- | --- |
| Credentials set; the final `EHLO` advertises no `AUTH` | `SMTP_AUTH_FAILED`, credentials never sent, `QUIT` | absent |
| Credentials set; `AUTH` advertised, but neither `PLAIN` nor `LOGIN` | same | absent |
| `235` | authenticated; continue | — |
| Any other reply to `AUTH` or to a `334` step (`535`, `534`, `538`, `454`, …) | `SMTP_AUTH_FAILED` (the step wins over the reply class) | the reply |
| No credentials; `MAIL FROM` answered `530` (authentication required) | `SMTP_AUTH_FAILED`, not `SMTP_REJECTED` | the reply |
| No credentials; the server advertises `AUTH` | no authentication; continue | — |

When no reply exists, `error.message` is the fixed string
`The SMTP server offers no supported authentication mechanism` (added to the fixed-message
table of `email/failures.md` for that case only).

#### Credentials require encryption

Credentials are only allowed with `encryption` `tls` or `ssl` (a password is never sent in
clear text):

- Validation on create and update (on the merged state for update): credentials with
  `encryption: none` → `400 VALIDATION_FAILED`, message
  `username and password require encryption tls or ssl`.
- Defensive check at send time as well (test email and `POST /v1/messages`), before any
  connection is opened: a row with credentials and `encryption: none` →
  `409 SMTP_INSECURE_CREDENTIALS` (new code in `email/failures.md` and
  `api/conventions/errors.md`), fixed message
  `The SMTP connection has credentials but no encryption; set encryption to tls or ssl`,
  no `error.smtp`; the server is never contacted (same pattern as
  `SMTP_PASSWORD_UNREADABLE`). The check runs before the password is decrypted.

### Decision 3 — Port / encryption mismatch

No validation of the port / encryption pair; mismatches fail naturally, fast, and with a hint.

| Mismatch | On the wire | Outcome |
| --- | --- | --- |
| `ssl` on a plain-text port (587, 25) | the clear-text `220` banner is read as a broken TLS record | `SMTP_TLS_FAILED`, immediately |
| `tls` / `none` on 465 (implicit TLS) | both sides wait silently (banner vs. ClientHello) | `SMTP_TIMEOUT`, bounded by the connect timeout |

- The wait for the `220` greeting is part of connecting: it is bounded by the **connect
  timeout**, not the command timeout. A greeting timeout maps to `SMTP_TIMEOUT` (TCP
  succeeded, so not `SMTP_CONNECTION_FAILED`).
- **Hint:** when the failure happens on a well-known mismatched pair, the fixed
  `error.message` gets a hint appended. The pairs are fixed: port 465 with `tls` / `none`,
  and `ssl` on port 587 or 25. Non-standard ports never get a hint. Messages:
  - `The SMTP server timed out; port 465 usually requires encryption ssl`
  - `TLS negotiation with the SMTP server failed; port 587 usually uses encryption tls`
    (same pattern for port 25)
- Not blocking: no create / update validation rejects these pairs (proxies, port forwards,
  non-standard providers stay possible).
- Follow-up (frontend, out of scope): an optional non-blocking warning in the admin form.
- Known ambiguity, accepted: `none` without credentials on 587 may get
  `530 5.7.0 Must issue a STARTTLS command first` at `MAIL FROM`; it maps to
  `SMTP_AUTH_FAILED` (Decision 2), and `error.smtp` shows the server's text. No
  text-based classification.

### Decision 4 — Replies (multi-line, malformed, text)

#### Parsing (RFC 5321 §4.2)

- Lines are `NNN-text` (continuation) and `NNN text` / `NNN` (last line); read until the
  last line. Every line of one reply must carry the same code.
- CRLF expected; a bare LF is tolerated on read (trailing CR stripped). Pidgey always writes
  CRLF.
- Limits: at most 100 lines per reply, at most 2048 bytes per line.
- No `PIPELINING`: one command, then its reply, then the next command.

#### Malformed replies

A line not starting with three digits followed by `-`, space or end of line; a code outside
200–599; mixed codes within one reply; a limit exceeded; or the peer closing the connection
mid-reply → **`502 SMTP_PROTOCOL_ERROR`** (new code in `email/failures.md` and
`api/conventions/errors.md`), fixed message `The SMTP server sent an invalid reply`, no
`error.smtp`. A close **between** replies (e.g. `421` then close) is not malformed: the
received reply is used normally.

#### Reply text

Used for both `error.smtp.message` and the success `smtp.message`:

- Drop the code and separator of every line and join the texts with one space.
- If every line starts with the same enhanced status code (e.g. `5.1.1`), keep it once, at
  the front.
- Then sanitize and cap (Decision 8).

Example: the three-line Gmail `550-5.1.1 …` reply becomes
`5.1.1 The email account that you tried to reach does not exist. Please try double-checking … - gsmtp`.

#### Related step mappings

- Success is the reply to the end of `DATA` (the lone `.` line), not to `QUIT`.
- `QUIT` is best-effort: its reply or failure never turns a sent message into a failure.
- Greeting other than `220`: `554` → `SMTP_REJECTED`; `421` or any other 4xx →
  `SMTP_TEMPORARY_FAILURE`; both with `error.smtp`.
- `EHLO` reply: first line is the server's greeting name; each later line is one extension
  (case-insensitive keyword plus parameters), read for `STARTTLS`, `AUTH`, `SMTPUTF8`,
  `SIZE`.

### Decision 5 — Timeout budget

PHP `max_execution_time` does not bound socket waits on Linux (CPU time only), and PHP cannot
read the Apache / proxy timeout, so Pidgey enforces its own budget. New keys in
`architecture/configuration.md` (durations: positive integers, seconds):

| Key | Default | Covers |
| --- | --- | --- |
| `SMTP_CONNECT_TIMEOUT` | `10` | TCP connect, the `ssl` handshake and the `220` greeting (Decision 3). |
| `SMTP_COMMAND_TIMEOUT` | `10` | Each command's reply, including the `STARTTLS` handshake and the reply to the final `.`. |
| `SMTP_TOTAL_TIMEOUT` | `25` | The whole session: a deadline fixed at connect time (`Pidgey\Infra\Clock`); every operation waits `min(its own timeout, time remaining)`. |

- Validation: `SMTP_CONNECT_TIMEOUT` and `SMTP_COMMAND_TIMEOUT` must each be
  `<= SMTP_TOTAL_TIMEOUT`; otherwise boot and `bin/check-config` refuse.
- Operator rule, in the deployment docs (architect, next to the reverse-proxy known gap):
  `SMTP_TOTAL_TIMEOUT` must stay below the web server / proxy request timeout.
- No separate `DATA` timeout (RFC 5321's 10 minutes cannot fit a synchronous request).
  Documented trade-off: a slow server may accept the message after Pidgey gave up, so an
  `SMTP_TIMEOUT` after the final `.` does not prove the message was not delivered.
- Timeout → code: before TCP is established → `SMTP_CONNECTION_FAILED`; anything after
  (handshake, greeting, any reply, the total deadline) → `SMTP_TIMEOUT`.
- Known gap, accepted: DNS resolution (`getaddrinfo`) blocks and is not bounded by the
  connect timeout.
- Resolves the `send-flow.md` / `configuration.md` inconsistency (the keys now exist).

### Decision 6 — Non-ASCII input

- **Addresses:** confirm the existing v1 rule of `api/conventions/validation.md` (no IDN, no
  Unicode local part): `fromAddress` and every recipient (test email `to`, later
  `POST /v1/messages`) are ASCII-only. Pidgey never sends `SMTPUTF8` and never converts an
  address domain; the send flow may rely on 7-bit addresses.
- **`host`:** ASCII only. A hostname is LDH labels (letters, digits, hyphen; 1–63 characters
  each, no leading / trailing hyphen), total at most 253 characters; IP literals unchanged.
  An IDN host must be entered in punycode (`smtp.xn--exmple-cua.de`). A Unicode host fails
  with the existing `host must be a hostname or an IP address`. No conversion at connect
  time and no `ext-intl` dependency. The LDH rule is added to the `host` field rule of
  `api/endpoints/pidgey-admin/smtp-connections.md` (it also makes the header-injection
  guarantee explicit).
- Future, not v1: IDN `host` conversion and `SMTPUTF8` addresses.

### Decision 7 — TLS certificate verification

Applies to `ssl` and to `tls` after `STARTTLS`.

- Always verify: `verify_peer=true`, `verify_peer_name=true`, `allow_self_signed=false`. No
  per-connection opt-out, no "skip verification" flag, no pinning.
- Peer name = the connection's `host` as stored.
  - Hostname: SNI sent with `host`; name matching per PHP / OpenSSL (SAN `dNSName`,
    wildcard only in the left-most label).
  - IP literal: must match an IP SAN, no CN fallback; no SNI (RFC 6066).
- **Minimum TLS 1.2** (`STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT`);
  TLS 1.0 / 1.1 refused (RFC 8996). Ciphers: OpenSSL defaults. Rationale for not allowing
  legacy TLS: weak crypto, OpenSSL 3 requires `@SECLEVEL=0` (weakens every handshake), not
  controllable on the shared host, untestable in CI, negligible benefit. Possible future
  feature: a per-connection "legacy TLS" opt-in.
- CA bundle: the system store by default (`openssl.cafile` / `openssl.capath` / OpenSSL
  default). Optional `SMTP_CA_FILE` (empty by default; new key in
  `architecture/configuration.md`): path to a PEM bundle that **replaces** the system store
  (to trust both, concatenate them). Test / CI set it to the test CA (#154). When set, the
  file must exist and be readable, else boot and `bin/check-config` refuse
  (`SMTP_CA_FILE: not readable`); contents are not parsed at boot.
- Any handshake, verification or protocol-version failure → `SMTP_TLS_FAILED` (fixed
  message, plus the Decision 3 hint when it applies). The OpenSSL reason (e.g.
  `certificate verify failed`) goes to the log line only, never the response.
- Out of scope for v1: per-connection custom CA (covered globally by `SMTP_CA_FILE`).

### Decision 8 — `failures.md` pending items

#### Sanitization and length cap

Applied to the joined reply text (Decision 4), for `error.smtp.message` and the success
`smtp.message`:

1. Keep printable ASCII (0x20–0x7E); every other byte becomes `?` (replies are untyped bytes;
   invalid UTF-8 would break `json_encode`).
2. Tabs become spaces, runs of spaces collapse to one, trim.
3. Cap at **512 characters**: a longer text is cut to 509 characters plus `...`.

#### Fixed `error.message` for every SMTP code

`error.message` is always a fixed string, never server text (server text lives only in
`error.smtp.message`). HTTP mapping confirmed (502 upstream rejected / broken, 503
temporary, 504 timeout, 409 fix the connection); no `Retry-After` on 503.

| Code | HTTP | `error.message` | `error.smtp` |
| --- | --- | --- | --- |
| `SMTP_INSECURE_CREDENTIALS` | 409 | `The SMTP connection has credentials but no encryption; set encryption to tls or ssl` | never |
| `SMTP_PASSWORD_UNREADABLE` | 409 | `The stored SMTP password could not be decrypted; set it again` | never |
| `SMTP_CONNECTION_FAILED` | 502 | `Could not connect to the SMTP server` | never |
| `SMTP_TLS_FAILED` | 502 | `TLS negotiation with the SMTP server failed` (+ Decision 3 hint) | the `STARTTLS` reply, if any |
| `SMTP_AUTH_FAILED` | 502 | `The SMTP server rejected the credentials`; without a reply: `The SMTP server offers no supported authentication mechanism`; `530` at `MAIL FROM` without credentials: `The SMTP server requires authentication` | the reply, if any |
| `SMTP_REJECTED` | 502 | by step: `The SMTP server rejected the connection` (greeting / `EHLO`), `The SMTP server rejected the sender` (`MAIL FROM`), `The SMTP server rejected the recipient` (`RCPT TO`), `The SMTP server rejected the message` (`DATA` / final `.`) | the reply |
| `SMTP_TEMPORARY_FAILURE` | 503 | `The SMTP server is temporarily unavailable; try again later` | the reply |
| `SMTP_PROTOCOL_ERROR` | 502 | `The SMTP server sent an invalid reply` | never |
| `SMTP_TIMEOUT` | 504 | `The SMTP server timed out` (+ Decision 3 hint) | never |

The strings are API contract: written into `email/failures.md` and
`api/conventions/errors.md` and asserted exactly by tests. This replaces the transport.md
mapping column "from the reply category".

### Decision 9 — No secret leaks

Secrets: the decrypted password, the `AUTH` payload (base64 of `\0user\0pass`, the `LOGIN`
password line), the encryption key (#151). The username is not secret, but is not logged
needlessly.

1. **`#[SensitiveParameter]`** (PHP `^8.4`) on every parameter carrying a secret (decrypt,
   transport credentials, the stream write of an `AUTH` line, …).
2. **Value object** for the password (e.g. `Pidgey\Email\SecretString`): `__toString`,
   `__debugInfo`, `__serialize`, `jsonSerialize` return `[redacted]`; the value is only
   obtained through one explicit method (e.g. `reveal()`), called where the `AUTH` line is
   built.
3. **Exception messages** are built only from fixed text, the step name and the server's
   reply — never from a line Pidgey sent.
4. **No protocol trace in v1.** The only log line is the per-attempt line (send-flow step 8):
   organization, connection id, token id (or the user for the test email), recipient,
   outcome (code, failing step, reply code, sanitized reply, OpenSSL reason for TLS
   failures). Never the body, the subject or credentials. A future debug trace must redact
   `AUTH` lines and `DATA` content.
5. **Echo redaction:** before a reply text is used (`error.smtp`, log), any occurrence of the
   password or of the base64 `AUTH` payload is replaced with `[redacted]`.
6. **Memory hygiene, best effort:** `sodium_memzero()` on the decrypted password after the
   session (PHP may hold copies; defense in depth, not a guarantee).
7. **Stack-trace arguments:** outside `development` / `test`, the web and CLI boot set
   `ini_set('zend.exception_ignore_args', '1')` (the setting is `INI_ALL`), so no trace
   carries arguments regardless of the host's `php.ini`. No `bin/check-config` check is
   needed.

**Test:** every failure path and the success path run with a canary password; neither the
password nor its base64 forms (alone or inside the `AUTH` payload) may appear in the
response body, any log record (message, context, exception trace) or any exception
message.

### Decision 10 — Hand-written transport and MIME builder

Both the SMTP transport and the MIME builder are hand-written over PHP streams; no new
runtime dependency (`architecture/dependencies.md` unchanged: `php` and `monolog/monolog`
only). Resolves the pending decision of `architecture/backend.md#configuration`.

Rationale: Decisions 1–9 and 13 pin behavior that libraries do not offer or fight (`symfony/mailer`
and PHPMailer are opportunistic about `STARTTLS`, have a single stream timeout and no
session deadline, classify failures by exception text, have their own MIME encoder choices
and need a real socket for tests). Considered and rejected: `symfony/mailer` +
`symfony/mime` (~8 packages), PHPMailer, and `symfony/mime` alone for MIME building.

Risk of protocol bugs is covered by reply-by-reply unit tests against a fake stream (the
`SmtpStream` abstraction) and by integration tests against Mailpit and the test CA (#154).

### Decision 11 — Namespaces and layers

- **`Pidgey\Email\`** — the send-flow feature (same style as `Pidgey\Auth\`): send flow,
  message, MIME builder, outcome, the `SecretString` value object, the send-time checks
  (`SMTP_INSECURE_CREDENTIALS`, `SMTP_PASSWORD_UNREADABLE`).
- **`Pidgey\Smtp\`** — the protocol layer: transport interface and implementation, the
  stream interface and socket implementation, reply and reply parser, session deadline,
  failure and failure code. Knows nothing about HTTP, organizations, repositories or
  logging; depends only on `Pidgey\Infra\Clock`.
- Dependencies point one way: `Pidgey\Email\` → `Pidgey\Smtp\`.
- The HTTP exception carrying `error.smtp` stays in `Pidgey\Http\` and maps the failure code
  to status and message.
- `architecture/backend.md#layers`: keep the `Pidgey\Smtp\` row; rewrite the "Services" row to
  match the code — business logic lives in feature namespaces (`Pidgey\Auth\`,
  `Pidgey\Email\`), no `Pidgey\Service\` namespace. Dependency rule: HTTP → feature
  namespaces → (Repositories, `Pidgey\Smtp\`).
- `implement-email/transport.md`: transport class proposals move from `Pidgey\Email\` to
  `Pidgey\Smtp\`. Exact class names stay proposals for `plan-issue`.
- Not decided here: where #151's password encryption service lives (its own plan).

### Decision 12 — `EHLO` argument

The `EHLO` argument is the **client's** name (RFC 5321 §4.1.4), never the connection's
`host`.

- New optional key `SMTP_EHLO_NAME` (empty by default) in `architecture/configuration.md`.
  When set it must be an ASCII LDH hostname (Decision 6 rule); otherwise boot and
  `bin/check-config` refuse (`SMTP_EHLO_NAME: invalid hostname`).
- When empty: the address literal of the socket's local address
  (`stream_socket_get_name($stream, false)` after connecting), e.g. `[203.0.113.7]` or
  `[IPv6:2001:db8::7]`.
- Not used: `gethostname()` (often not an FQDN on a shared host, leaks internals),
  `FRONTEND_BASE_URL`'s host, the connection's `host`.
- The same name is used for the second `EHLO` after `STARTTLS`.
- Deployment docs (architect): recommend setting `SMTP_EHLO_NAME` to the host's public
  FQDN, ideally matching its reverse DNS.
- Fix the field rules of `api/endpoints/pidgey-admin/smtp-connections.md`: `host` is the
  connect target and TLS peer name, not the `EHLO` argument.

### Decision 13 — MIME message

Rules go into the permanent spec (`email/message-scope.md`), so `POST /v1/messages` reuses
them; the test email uses `text` only.

#### Headers (in order)

| Header | Value |
| --- | --- |
| `Date` | RFC 5322 date from `Pidgey\Infra\Clock`, UTC (`Sat, 07 Nov 2026 14:03:00 +0000`). |
| `From` | `fromAddress`, bare address (no display name in v1). |
| `To` | the recipient, bare address. |
| `Subject` | printable ASCII as-is; otherwise RFC 2047 encoded-words (`=?UTF-8?B?…?=`), each at most 75 characters, never splitting a UTF-8 character; folded at 78 characters. |
| `Message-ID` | `<` 32 hex characters (`random_bytes(16)`) `@` the `fromAddress` domain `>`. |
| `MIME-Version` | `1.0`. |
| `Content-Type` / `Content-Transfer-Encoding` | see Body. |

- Not added: `X-Mailer` / `User-Agent` (fingerprinting), `Reply-To`, `Sender`,
  `Return-Path` (set by the receiver), DKIM (the relay's job).
- Validation: `subject` must not contain control characters (CR, LF, TAB included) →
  `subject must not contain control characters` (header injection).

#### Body

- `text` only: `text/plain; charset=UTF-8`, `quoted-printable`.
- `html` only: `text/html; charset=UTF-8`, `quoted-printable`.
- Both: `multipart/alternative; boundary="=_pidgey_<32 hex>"`, text part first, HTML part
  last (RFC 2046: last is preferred). `=_` never occurs in quoted-printable output, so the
  boundary cannot collide.
- Line endings normalized to CRLF before encoding (lone LF or CR → CRLF).
- Always quoted-printable, even for pure ASCII (one code path): the message is 7-bit, lines
  ≤ 76 characters, `8BITMIME` / `BODY=8BITMIME` never used.

#### `DATA` transmission (transport)

- Dot-stuffing (RFC 5321 §4.5.2): a line starting with `.` gets an extra `.`; done in the
  transport, not the MIME builder. The message ends with `CRLF.CRLF`.
- No `SIZE` parameter on `MAIL FROM`; an oversized message is rejected by the server
  (`552` → `SMTP_REJECTED`, "rejected the message"). An API body-size limit stays a pending
  item of `POST /v1/messages`, outside this issue.

## Solution

### Where to write

- Decisions go into the permanent specs (`email/send-flow.md`, `email/failures.md`,
  `architecture/configuration.md` for new keys), removing the matching Pending decisions.
- Update `docs/agents/specs/implement-email/transport.md` with the resulting implementation
  work (classes, interfaces such as a stream abstraction usable by a fake server in tests).

### Scope (final)

Besides the nine decisions listed above, this issue also settles the open items that
`implement-email/transport.md` assigns to #152: the hand-written transport (Decision 10),
namespaces (Decision 11), the `EHLO` argument (Decision 12) and the MIME message
(Decision 13). Every Pending decision of `implement-email/transport.md` is resolved here.

#### Specs touched (product-owner)

- `email/send-flow.md` — steps 5–7 per Decisions 1–5, 9, 12; timeouts reference the keys.
- `email/failures.md` and `api/conventions/errors.md` — the full code table of Decision 8,
  new codes `SMTP_INSECURE_CREDENTIALS`, `SMTP_PROTOCOL_ERROR`; pending items removed.
- `email/smtp-connections.md` — `AUTH` mechanisms, credentials require `tls` / `ssl`, TLS
  policy (Decision 7).
- `email/message-scope.md` — MIME rules (Decision 13), `subject` control-character rule.
- `api/endpoints/pidgey-admin/smtp-connections.md` — `host` LDH rule and `EHLO` fix,
  credentials-with-`none` validation; the "cleartext with `encryption: none`" risk note is
  now closed.
- `architecture/configuration.md` — `SMTP_CONNECT_TIMEOUT`, `SMTP_COMMAND_TIMEOUT`,
  `SMTP_TOTAL_TIMEOUT`, `SMTP_CA_FILE`, `SMTP_EHLO_NAME` and their check-config failures;
  `zend.exception_ignore_args` at boot.
- `architecture/backend.md` — layers (Decision 11), transport pending decision removed.
- `implement-email/transport.md` — open items checked off, building blocks and names
  updated (`Pidgey\Smtp\`), implementation work listed.

#### Deployment docs (architect)

`SMTP_TOTAL_TIMEOUT` below the web / proxy timeout; `SMTP_EHLO_NAME` recommendation;
`SMTP_CA_FILE` usage.

### Backward compatibility

No data migration for stored connections that violate the new rules:

- Credentials with `encryption: none` → send-time `409 SMTP_INSECURE_CREDENTIALS`
  (Decision 2).
- A non-LDH `host` → fails naturally at connect time (`SMTP_CONNECTION_FAILED`); no
  dedicated check.
- Updating such a row: validation runs on the merged state, so any update fails with `400`
  until `encryption` / `host` is fixed. List, show, activate and delete keep working.
- The `subject` rule only affects requests, never stored data.

### Out of scope

Code; encryption, endpoint hardening and test infrastructure (other #149 issues);
`POST /v1/messages`.

### Acceptance

- No transport-related Pending decision is left in `email/send-flow.md`, `email/failures.md`
  or `implement-email/transport.md`.
- Every SMTP step has a defined success and failure mapping, testable reply by reply.

## Benefits

- Every SMTP step has a defined, testable success and failure mapping, reply by reply.
- No downgrade, verified TLS 1.2+, credentials never sent in clear text, no secret leaks.
- Every request stays within a hard time budget below the web server timeout.
- No new runtime dependency.
