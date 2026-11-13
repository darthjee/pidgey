# Issue: SMTP transport session

## Description

Part of epic #149 (email integration). Split from #163, as settled by #152. It implements the SMTP
**session** (`Pidgey\Smtp\SmtpTransport`) on top of the primitives and the socket stream from
#163. It is the transport the send flow (#165) runs. It has no HTTP surface and does no logging.

Specs:

- `docs/agents/specs/email/send-flow.md#smtp-session` (connect and greeting, `EHLO`,
  `STARTTLS`, `AUTH`, envelope and `DATA`, replies, port / encryption mismatch),
  `#timeouts` and `#secret-hygiene`;
- `docs/agents/specs/email/failures.md#classification-rules`;
- `docs/agents/specs/email/smtp-connections.md#authentication` and `#tls-policy`;
- `docs/agents/specs/email/testing.md` (fake SMTP server, required coverage, canary password);
- `docs/agents/specs/architecture/backend.md#layers` (`Pidgey\Smtp\` depends only on
  `Pidgey\Infra\`);
- implementation notes: `docs/agents/specs/implement-email/transport.md` (items 4 and 11,
  plus the Transport, Stream, Result and Failure shared contracts) and
  `implement-email/testing.md`.

## Problem

#163 delivered the parts of a session but not the session itself:

- `SmtpStream` / `SocketStream`, `ReplyParser` / `Reply`, `EhloExtensions`, `Deadline`,
  `SessionSettings`, `TlsContext`, `StreamFailure` and `SecretString`;
- the test doubles `FakeSmtpStream`, `FakeSmtpServer` and `Mailpit`.

Nothing runs the step sequence yet, and nothing enforces the no-downgrade and injection
guards or authenticates. Stream failures and replies are not classified into the `SMTP_*`
failure codes. The send flow (#165) is blocked until that exists.

## Expected Behavior

`SmtpTransport::send(target, settings, credentials, envelope, message)` runs exactly one
session, as `send-flow.md#smtp-session` describes. It sends one command, reads its reply,
then sends the next (no `PIPELINING`):

1. **Open:** connect to the checked IP and `port`. `Implicit` mode runs the TLS handshake
   at once, with `host` as the peer name. Connect, handshake and greeting share the connect
   timeout. The session deadline starts from `SMTP_TOTAL_TIMEOUT` at connect time.
2. **Greeting:**
   - `220` continues;
   - `554` → `SMTP_REJECTED`;
   - `421` or any other 4xx → `SMTP_TEMPORARY_FAILURE`;
   - the peer closing before sending any byte → `SMTP_CONNECTION_FAILED`.
3. **`EHLO`:** the argument is `SMTP_EHLO_NAME` when set, else the local address literal
   (`[203.0.113.7]`, `[IPv6:2001:db8::7]`), never the connection's `host`. There is no `HELO`
   fallback: 5xx → `SMTP_REJECTED`, 4xx → `SMTP_TEMPORARY_FAILURE`.
4. **`STARTTLS`** (`StartTls` mode only). There is never a downgrade:
   - `STARTTLS` not advertised → `SMTP_TLS_FAILED` and a best-effort `QUIT`;
   - any reply but `220` → `SMTP_TLS_FAILED`, with the reply;
   - bytes pending before the handshake → `SMTP_TLS_FAILED`, session aborted;
   - handshake or certificate failure → `SMTP_TLS_FAILED`, closed without `QUIT`, with the
     OpenSSL reason kept for the log only;
   - after the upgrade, a second `EHLO` with the same name; the pre-TLS extension list is
     discarded.
5. **`AUTH`** (only when credentials are given; mechanisms come from the last `EHLO`):
   - `PLAIN` with an initial response first, else `LOGIN` (two `334` prompts, answered
     without parsing their text);
   - no `AUTH` advertised, or neither mechanism offered → `SMTP_AUTH_FAILED`, credentials
     never sent, `QUIT`;
   - any reply other than `235`, to `AUTH` or to a `334` step → `SMTP_AUTH_FAILED` (the step
     wins over the reply class);
   - a `PLAIN` rejection is never retried with `LOGIN`;
   - without credentials, nothing is sent even if `AUTH` is advertised.
6. **Envelope:**
   - `MAIL FROM:<from>` (no `SIZE`, `BODY` or `SMTPUTF8` parameter): `530` →
     `SMTP_AUTH_FAILED`, other 5xx → `SMTP_REJECTED`, 4xx → `SMTP_TEMPORARY_FAILURE`;
   - `RCPT TO:<recipient>` for each recipient (one in v1): same 5xx / 4xx rule.
7. **`DATA`:**
   - `DATA` expects `354`;
   - the message is split on `\r\n`, `\r` or `\n` and each line is rewritten with CRLF, so a
     bare CR or LF never reaches the wire (SMTP smuggling); lines starting with `.` are
     dot-stuffed;
   - the message ends with `CRLF.CRLF`;
   - a rejection of `DATA` or of the final `.` → `SMTP_REJECTED` (4xx →
     `SMTP_TEMPORARY_FAILURE`);
   - a 2xx reply to the final `.` is success.
8. **`QUIT`:** best-effort after success. Its reply, failure, timeout or close never turns a
   sent message into a failure. The stream is always closed.

Across all steps:

- **Timeouts:** each reply has one budget, `min(command timeout, session time remaining)`,
  shared by all its lines; writes and the `STARTTLS` handshake are bounded the same way.
  Before TCP is established → `SMTP_CONNECTION_FAILED`; afterwards → `SMTP_TIMEOUT`.
- **Bad replies:** a malformed reply, or a close mid-reply, → `SMTP_PROTOCOL_ERROR`. A close
  with no reply after the greeting → `SMTP_PROTOCOL_ERROR`, but during `QUIT` it is ignored.
- **Secret hygiene:**
  - the password travels as a `SecretString` and is revealed only where the `AUTH` line is
    built;
  - every secret-carrying parameter is `#[SensitiveParameter]`;
  - exception messages are built from fixed text, the step and the failure code only, never
    from a line Pidgey sent;
  - the transport does not wipe the password (the send flow owns and wipes it).

## Solution

All new classes live in `Pidgey\Smtp\`. They depend only on `Pidgey\Infra\` (`Clock`,
`SecretString`), never on `Pidgey\Model\`, `Env`, repositories or the logger.

- **Inputs** (readonly value objects):
  - `TlsMode` enum: `Plain`, `StartTls`, `Implicit`. The send flow (#165) maps the model's
    `none` / `tls` / `ssl` onto it, so the transport never sees `Model\SmtpEncryption`;
  - `Target`: `host` (TLS peer name and SNI), the checked IP, `port` and `TlsMode`;
  - `Credentials`: `username` and the password as a `#[SensitiveParameter]` `SecretString`.
    It implements neither `__toString` nor `JsonSerializable`, and its `__debugInfo` hides
    the password;
  - `Envelope`: the sender and the recipient list;
  - `SessionSettings` (from #163) and the MIME message as a string (not yet dot-stuffed).
- **Interface:** `Transport` with `send(Target, SessionSettings, ?Credentials, Envelope,
  string $message): SmtpResult`. The implementation is `SmtpTransport`, built with an
  `SmtpStream` factory (or a stream) and `Clock`, so unit tests plug in `FakeSmtpStream` and
  `FrozenClock`.
- **Success:** `SmtpResult` carries the **raw** `Reply` to the final `.` (code and lines).
  Redaction, sanitization and the enhanced-status join are #165's job: the transport never
  formats reply text for output.
- **Failure:** `SmtpFailure` (a `RuntimeException`), thrown by `send()`. It carries:
  - `failureCode()`: the `FailureCode` enum with the seven transport codes
    (`SMTP_CONNECTION_FAILED`, `SMTP_TLS_FAILED`, `SMTP_AUTH_FAILED`, `SMTP_REJECTED`,
    `SMTP_TEMPORARY_FAILURE`, `SMTP_PROTOCOL_ERROR`, `SMTP_TIMEOUT`);
  - `step()`: the `SmtpStep` enum (`Connect`, `Greeting`, `Ehlo`, `StartTls`, `Auth`,
    `MailFrom`, `RcptTo`, `Data`, `EndOfData`);
  - `reply()`: the raw `Reply`, or null when no reply was received;
  - `reason()`: the OpenSSL reason, TLS only, or null.

  Its message is fixed text such as `SMTP <step>: <code>`. The three `SMTP_AUTH_FAILED`
  situations are told apart from the step and the reply, with no extra field:
  - `Auth` with a reply: credentials rejected;
  - `Auth` without a reply: no supported mechanism;
  - `MailFrom` with `530`: authentication required.
- **Classification:** `StreamFailure` and `MalformedReply` from #163 are mapped by kind and
  step, as in `failures.md#classification-rules`.
- **Tests:**
  - **Unit, on `FakeSmtpStream` with `FrozenClock`, reply by reply:**
    - greeting codes and a close at the greeting;
    - the `EHLO` name (setting and local-address literal, IPv4 and IPv6) and no `HELO`
      fallback;
    - `STARTTLS`: missing, refused, injected bytes, handshake failure, the second `EHLO`
      with the pre-TLS list discarded;
    - `AUTH`: `PLAIN`, `LOGIN`, no supported mechanism, rejected `PLAIN` not retried with
      `LOGIN`, `530` at `MAIL FROM`, no credentials with `AUTH` advertised;
    - envelope and `DATA` rejections, 4xx and 5xx;
    - dot-stuffing and bare CR / LF normalization;
    - multi-line, malformed, closed and mid-reply-close replies;
    - per-reply budgets and the session deadline;
    - best-effort `QUIT`.
  - **Integration on Mailpit** (`plain`, `starttls`, `ssl`):
    - a delivered message with the right credentials, checked in the mailbox;
    - wrong credentials → `SMTP_AUTH_FAILED`;
    - `530` without credentials;
    - the test CA via `SMTP_CA_FILE`; verification is never disabled.
  - **Integration on `FakeSmtpServer`:** `STARTTLS` not advertised, no supported `AUTH`,
    multi-line, malformed and 4xx replies, injected bytes, an untrusted or wrong-name
    certificate, timeouts.
  - **End-to-end coverage:** every transport `FailureCode`, with its step and reply. The
    HTTP status, fixed message and `error.smtp` columns stay with #165
    (`SmtpFailureException`).
  - **Canary password**, on every success and failure path: the password and its base64
    forms (alone, and inside the `PLAIN` payload and the `LOGIN` line) never appear in an
    `SmtpFailure` message or in any thrown exception message. Response bodies and log
    records are covered by #165, since the transport does not log.

Not in scope:

- MIME building, `SendFlow` and `HostPolicy`;
- reply redaction and sanitization;
- the attempt log;
- the HTTP mapping (`SmtpFailureException`): all #165.

Agents: **backend** implements; **email** reviews the protocol behavior.

## Benefits

- Unblocks #165 (send flow), and through it #166 (test email) and `POST /v1/messages`.
- Every transport failure path is pinned by a test before any HTTP mapping is built on top.
- `STARTTLS` stripping, `STARTTLS` command injection and SMTP smuggling are closed in the
  protocol layer, whatever its callers do.
- The transport stays free of HTTP, logging and model types, so it can be tested in
  isolation.

## Acceptance

- Every transport failure path of `email/failures.md` is covered by a unit or fake-server
  test; TLS verification is never disabled.
- `implement-email/transport.md`:
  - item **4** is ticked in the same PR;
  - item **11** stays open with a note: the transport tests are done, and the leak test
    through responses and logs waits for #165.

## Dependencies

#163 (merged). Blocks #165.
