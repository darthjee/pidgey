# The SMTP session

Add the `Transport` interface and `SmtpTransport`, following
`docs/agents/specs/email/send-flow.md#smtp-session` and `#timeouts`, and
`email/failures.md#classification-rules`. Wire it in `Container::smtpTransport()`.

## Session, in order

Every reply is read with
`ReplyParser::read($stream, $session->within($settings->commandTimeout))`: one budget per
reply, capped by the session deadline. Writes use the same per-command budget.

1. **Session deadline.** `Deadline::start($clock, $settings->totalTimeout)`. Then
   `$connect = $session->within($settings->connectTimeout)` bounds `open()` and the greeting.
2. **Open.** `$stream->open($target->address, $target->port, $target->host,
   $target->tlsMode === TlsMode::Implicit, $settings->caFile, $connect)`.
3. **Greeting**, read with the connect budget:
   - `220` continues;
   - `554` → `SMTP_REJECTED`;
   - any 4xx → `SMTP_TEMPORARY_FAILURE`;
   - any other code → `SMTP_REJECTED` for 5xx, `SMTP_PROTOCOL_ERROR` for a non-error code
     other than `220`. Pick the spec's nearest rule and note it in a docblock.
4. **`EHLO`.** The name is `$settings->ehloName`, or the address literal of
   `$stream->localAddress()`: `[a.b.c.d]` for IPv4, `[IPv6:…]` for IPv6 (detect with
   `filter_var(FILTER_FLAG_IPV6)`).
   - A non-`250` reply: 5xx → `SMTP_REJECTED`, 4xx → `SMTP_TEMPORARY_FAILURE`.
   - There is no `HELO` fallback.
   - Parse the extensions with `EhloExtensions::fromReply()`.
5. **`STARTTLS`** (only for `TlsMode::StartTls`):
   - `STARTTLS` not advertised → `SMTP_TLS_FAILED` with no reply, then a best-effort `QUIT`;
   - a reply other than `220` → `SMTP_TLS_FAILED` with that reply;
   - otherwise `upgradeToTls($target->host, $settings->caFile, <command budget>)`.
     `StreamFailure` `Tls` or `UnexpectedData` → `SMTP_TLS_FAILED` (keep `reason()`) and
     close without `QUIT`; `Timeout` → `SMTP_TIMEOUT`;
   - then `EHLO` again with the same name, and replace the extensions with the new list.
6. **`AUTH`** (only when `$credentials !== null`):
   - Mechanisms come from the current extensions. If `PLAIN` is offered, send
     `AUTH PLAIN base64("\0" . user . "\0" . reveal())`. Else, if `LOGIN` is offered, send
     `AUTH LOGIN`, expect `334`, send `base64(user)`, expect `334`, send `base64(reveal())`.
   - Neither mechanism, or no `AUTH` → `SMTP_AUTH_FAILED` with no reply, then `QUIT`.
   - Any reply other than `235` (or other than `334` at a `LOGIN` step) → `SMTP_AUTH_FAILED`
     with the reply. Never retry with `LOGIN`.
   - Build each secret line in a local variable passed straight to `writeLine()`, and keep
     it out of any exception or property.
7. **`MAIL FROM:<from>`**, with no parameters:
   - `530` → `SMTP_AUTH_FAILED`, with or without credentials (the spec names the case
     without credentials; with credentials, a `530` after a `235` is still an auth problem);
   - other 5xx → `SMTP_REJECTED`;
   - 4xx → `SMTP_TEMPORARY_FAILURE`.
8. **`RCPT TO:<rcpt>`** for each recipient, with the same mapping minus the `530` rule.
9. **`DATA`:**
   - expect `354`;
   - split `$message` with `preg_split('/\r\n|\r|\n/', …)`; drop the one empty trailing
     element when the message ends with a line break;
   - for each line, prefix `.` when it starts with `.`, then `writeLine()` it;
   - send `.`;
   - the final reply must be 2xx (step `EndOfData`); 5xx → `SMTP_REJECTED`, 4xx →
     `SMTP_TEMPORARY_FAILURE`.
10. **`QUIT`.** On success, write `QUIT` and read the reply inside a `try` that swallows
    `StreamFailure` and `MalformedReply`. On a failure path that calls for `QUIT`, do the
    same, then throw the original failure. In every case, close the stream in a `finally`.

## Stream failure → code, by step

| Stream outcome | Step | Code |
| --- | --- | --- |
| `Connect` | any | `SMTP_CONNECTION_FAILED` |
| `Tls` | `Connect` | `SMTP_TLS_FAILED` |
| `Tls` / `UnexpectedData` | `StartTls` | `SMTP_TLS_FAILED` |
| `Timeout` | before TCP (inside `open()` for a plain connect, i.e. `Connect` reported by the stream) | `SMTP_CONNECTION_FAILED` |
| `Timeout` | `Connect` with implicit TLS, or after TCP | `SMTP_TIMEOUT` |
| `Closed` | `Greeting` | `SMTP_CONNECTION_FAILED` |
| `Closed` | any later step | `SMTP_PROTOCOL_ERROR` |
| `MalformedReply` | any | `SMTP_PROTOCOL_ERROR` |

`open()` throws `Timeout` when the deadline ran out before or during connect. Check how
`SocketStream::open()` reports a TCP connect timeout (kind `Connect` or `Timeout`) and map so
that a timeout before TCP is established is `SMTP_CONNECTION_FAILED`. Implicit-TLS handshake
timeouts are `SMTP_TIMEOUT`.

## Container

Add `Pidgey\Container::smtpTransport(): Transport`, memoized:
`new SmtpTransport($this->clock())`.

## Unit tests

Write `source/tests/unit/Smtp/SmtpTransportTest.php` on `FakeSmtpStream` + `FrozenClock`,
reply by reply, one test or data-provider row per rule. Use small helpers for the happy
prefix (open, greeting, `EHLO`). Cover:

- **Greeting:** `220`; `554`; `421`; `450`; `Closed` before any byte; greeting timeout
  (`SMTP_TIMEOUT`); open failure `Connect`.
- **`EHLO`:** the name from settings; the IPv4 and IPv6 literals (`FakeSmtpStream`'s
  `localAddress` argument); `550` with no `HELO` attempt; `421`.
- **`STARTTLS`:**
  - not advertised: `SMTP_TLS_FAILED`, `QUIT`, no reply;
  - `454`: reply kept;
  - upgrade failing with `Tls`: reason kept, no `QUIT`;
  - `UnexpectedData`;
  - the second `EHLO` sent, and only the post-TLS `AUTH` list used (the pre-TLS list offers
    `PLAIN`, the post-TLS list only `LOGIN` → `LOGIN` is used);
  - the peer name is `host` and the CA file comes from settings (`upgrades()`).
- **Implicit TLS:** `opens()[0]['implicitTls'] === true` and no `STARTTLS` sent.
- **`AUTH`:**
  - the exact `PLAIN` payload;
  - the `LOGIN` sequence;
  - `PLAIN` rejected with `535` and no `LOGIN` attempt;
  - `LOGIN` rejected at the password step;
  - no `AUTH` advertised, and only `CRAM-MD5` offered: credentials never written;
  - no credentials with `AUTH` advertised: nothing sent;
  - `454` at `AUTH` → `SMTP_AUTH_FAILED`.
- **Envelope and `DATA`:**
  - `530` at `MAIL FROM` → `SMTP_AUTH_FAILED`;
  - `550` / `451` at `MAIL FROM`, `RCPT TO`, `DATA` and the final `.`, with the step checked;
  - `MAIL FROM` sent with no parameters.
- **Dot-stuffing and line endings:** lines `.x` and `.`; bare `\n` and `\r` in the message
  are rewritten so each becomes its own CRLF line; `"a\n.\nb"` cannot end `DATA` early.
- **Replies:** a multi-line final reply is returned whole in `SmtpResult`; a malformed reply
  and a mid-reply close → `SMTP_PROTOCOL_ERROR`; `Closed` at `RCPT TO` →
  `SMTP_PROTOCOL_ERROR`.
- **Timeouts:**
  - a reply delayed beyond the command timeout → `SMTP_TIMEOUT`;
  - the session deadline: with the total below the sum of the per-command waits, a later
    reply times out early;
  - a deadline already expired fails with no I/O.
- **`QUIT`:** a `QUIT` reply of `500`, a `Closed` failure and a timeout all still return
  success.
- **Close:** `isClosed()` is true after every path.
- **Canary:** run the success path and each `AUTH` failure path with the password
  `canary-p4ss` and assert, through a shared helper, that no `SmtpFailure` message (or
  previous message) contains:
  - the password;
  - `base64_encode` of the password;
  - the whole `PLAIN` payload.

## Files to Change

- `source/lib/Smtp/Transport.php`: new interface.
- `source/lib/Smtp/SmtpTransport.php`: new.
- `source/lib/Container.php`: `smtpTransport()`.
- `source/tests/unit/Smtp/SmtpTransportTest.php`: new.
- `source/tests/unit/ContainerTest.php` (or the existing container test): `smtpTransport()`
  is memoized.
