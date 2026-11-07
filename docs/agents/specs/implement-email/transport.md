# Implement Email: SMTP Transport and Send Flow

Purpose: map the SMTP transport and the synchronous send flow to their implementation work,
define the contracts shared by several implementation issues, and list the open decisions
owned by #152.

> Temporary — part of epic #149. Permanent specs win on conflict; update them, not this
> file, when a decision changes the target behavior.

Index: [implement-email.md](../implement-email.md).

## Open items

- [ ] **Pending decision:** `tls` when the server does not advertise `STARTTLS` in its `EHLO`
  reply. *Working assumption, to be confirmed in #152:* fail with `SMTP_TLS_FAILED` and never
  fall back to plain text (no downgrade).
- [ ] **Pending decision:** supported `AUTH` mechanisms
  ([email/smtp-connections.md](../email/smtp-connections.md) says `LOGIN` / `PLAIN`): the
  preference order, and the code when credentials exist but the server offers no `AUTH`, or
  none of the supported mechanisms (e.g. `SMTP_AUTH_FAILED` without sending credentials).
  Whether `AUTH` is refused over an unencrypted channel (`encryption: none`).
- [ ] **Pending decision:** port / encryption mismatch (e.g. `ssl` on port 587, `tls` or
  `none` on port 465): no validation and a natural failure (TLS error or timeout), a
  validation warning, or a dedicated fast failure; which `SMTP_*` code each case ends in.
- [ ] **Pending decision:** multi-line replies (`250-…` continuation lines): how they are
  parsed, and which text feeds `error.smtp.message` and the success `smtp.message` (the last
  line, or every line joined).
- [ ] **Pending decision:** timeouts. The keys and defaults for the connect timeout and the
  per-command timeout (and whether `DATA` gets its own), and how they relate to PHP
  `max_execution_time` and the Apache / proxy request timeout, so the request answers
  `SMTP_TIMEOUT` before the web server cuts it.
- [ ] **Pending decision (inconsistency):** [email/send-flow.md](../email/send-flow.md) says
  the timeouts are configurable, but
  [architecture/configuration.md](../architecture/configuration.md) lists no timeout key.
- [ ] **Pending decision:** IDN hostnames and non-ASCII addresses: whether a Unicode `host` is
  converted to punycode before connecting, and whether a non-ASCII `fromAddress` or `to` is
  rejected by validation, converted (punycode domain), or sent with `SMTPUTF8` when the server
  advertises it.
- [ ] **Pending decision:** TLS certificate verification policy (pending in
  [email/smtp-connections.md](../email/smtp-connections.md)). *Working assumption, to be
  confirmed in #152:* always verify, peer name = the connection's `host`, system CA bundle;
  no per-connection opt-out. Whether a custom CA bundle can be configured, and how an IP
  literal `host` is verified.
- [ ] **Pending decision:** the pending items of [email/failures.md](../email/failures.md)
  (and [api/conventions/errors.md](../api/conventions/errors.md#smtp-send-failures)): the
  maximum length of `error.smtp.message` after sanitization, the exact wording of the fixed
  messages for `SMTP_CONNECTION_FAILED` / `SMTP_TLS_FAILED` / `SMTP_TIMEOUT`, and the exact
  HTTP status mapping (working assumption in that table).
- [ ] **Pending decision:** secret hygiene. No `AUTH` payload and no decrypted password may
  appear in exceptions, exception messages, stack traces (`#[SensitiveParameter]`), debug
  output or any log line, including the protocol trace if one is kept for debugging.
- [ ] **Pending decision:** hand-written transport over PHP streams or an SMTP library
  (pending in [architecture/backend.md](../architecture/backend.md#configuration); working
  assumption there: hand-written, PHP streams). A library changes
  [architecture/dependencies.md](../architecture/dependencies.md).
- [ ] **Pending decision (inconsistency):** namespace and layer.
  [architecture/backend.md](../architecture/backend.md#layers) names the transport layer
  `Pidgey\Smtp\` and a `Pidgey\Service\` layer for "sending a message", while the existing
  code has no `Service` namespace (business logic lives in e.g. `Pidgey\Auth\`). This file
  proposes `Pidgey\Email\`; the permanent spec must be aligned either way.
- [ ] **Pending decision (inconsistency):** `EHLO` argument. The field rules of
  [api/endpoints/pidgey-admin/smtp-connections.md](../api/endpoints/pidgey-admin/smtp-connections.md#field-rules)
  say `host` ends up in `EHLO`, but `EHLO` carries the client's name. Which name Pidgey sends
  (a configured name, the server hostname, or an address literal) is undecided.
- [ ] **Pending decision:** MIME headers Pidgey adds beyond `From:`, `To:` and `Subject:`
  (`Date`, `Message-ID` and its domain, `MIME-Version`, `Content-Type` / transfer encoding of
  the body, encoded-word for a non-ASCII subject), and dot-stuffing / line-length handling of
  the body ([email/message-scope.md](../email/message-scope.md)).

## Building block: SMTP transport

- **Responsibility:** runs one SMTP session against a connection: open the stream (`ssl`
  implicit TLS, `tls` plain then `STARTTLS`, `none` plain), `EHLO`, `AUTH` when credentials
  exist, `MAIL FROM` / `RCPT TO` / `DATA`, `QUIT`, and returns the final reply or a
  categorized failure. It knows nothing about HTTP, organizations or logging.
- **Proposed names:** `Pidgey\Email\SmtpTransport` (interface `Pidgey\Email\Transport`),
  `Pidgey\Email\SmtpStream` (stream abstraction) with a socket implementation
  `Pidgey\Email\SocketStream`, `Pidgey\Email\SmtpReply`, `Pidgey\Email\SendFailure`,
  `Pidgey\Email\FailureCode`. Proposals only.
- **Collaborators:** `Pidgey\Model\SmtpConnection`, `Pidgey\Model\SmtpEncryption`, the
  password encryption service ([encryption.md](encryption.md)), `Pidgey\Infra\Clock` (deadlines),
  `Pidgey\Infra\Env` (timeouts).
- **Governing specs:** [email/send-flow.md](../email/send-flow.md) (steps 5–7, timeouts);
  [email/smtp-connections.md](../email/smtp-connections.md) (`encryption`, credentials, TLS
  policy); [email/failures.md](../email/failures.md);
  [architecture/backend.md](../architecture/backend.md#layers).
- **Open items:** every item above except the MIME headers item.

## Building block: send flow

- **Responsibility:** given a connection, a recipient and the message fields, builds the MIME
  message, decrypts the credentials, runs the transport, maps the outcome to a success body or
  an `SMTP_*` error, and writes the send-flow log line (step 8 of
  [email/send-flow.md](../email/send-flow.md)). It does not resolve the active connection
  (that is `POST /v1/messages`, out of scope) nor load the connection (the caller does).
- **Proposed names:** `Pidgey\Email\SendFlow` (or `Pidgey\Email\Mailer`),
  `Pidgey\Email\Message`, `Pidgey\Email\MimeBuilder`, `Pidgey\Email\SendOutcome`, and an HTTP
  exception `Pidgey\Http\SmtpFailureException` carrying `error.smtp`. Proposals only.
- **Collaborators:** the SMTP transport (above), the password encryption service, the
  application logger (monolog, see [endpoints.md](endpoints.md#building-block-operator-log)),
  `Pidgey\Http\HttpException` / `Pidgey\Http\ErrorResponse` / `Pidgey\Http\ErrorFormatter` (the
  error envelope).
- **Governing specs:** [email/send-flow.md](../email/send-flow.md) (steps 5–8);
  [email/message-scope.md](../email/message-scope.md);
  [email/failures.md](../email/failures.md);
  [api/conventions/errors.md](../api/conventions/errors.md#smtp-send-failures).
- **Open items:** the MIME headers item, the `failures.md` item and the secret-hygiene item
  above.

## Shared contracts

Described by what each carries; signatures are left to `plan-issue`.

### Transport

| Direction | Carries |
| --- | --- |
| Input: connection settings | `host`, `port`, `encryption` (`none` / `tls` / `ssl`), timeouts. |
| Input: credentials | `username` and the **decrypted** password, or none. Never logged. |
| Input: envelope | the sender (`fromAddress`) and the recipient list (one address in v1). |
| Input: message | the complete MIME message (headers and body), ready for `DATA`. |
| Output | a **result** (success) or a **failure** (below). |

### Stream

The transport reads and writes through a stream abstraction, so a fake SMTP server plugs in
without a socket (unit tests) and a socket fixture or Mailpit plugs in for integration tests
(see [testing.md](testing.md)). It carries:

- **open** a target (host, port, implicit TLS or not) within the connect timeout;
- **write** a line, **read** a line, each within the command timeout;
- **upgrade** to TLS in place (for `STARTTLS`), with the verification policy;
- **close**;
- each operation either succeeds or reports a stream-level failure: connect failure, TLS
  failure, timeout, or connection closed by the peer.

### Result

| Field | Meaning |
| --- | --- |
| `code` | The SMTP reply code of the final reply (e.g. `250`). |
| `message` | The reply text, sanitized (control characters stripped, length capped). |

It becomes the success body `{ "status": "sent", "smtp": { "code", "message" } }`.

### Failure

| Field | Meaning |
| --- | --- |
| `failureCode` | One of `SMTP_CONNECTION_FAILED`, `SMTP_TLS_FAILED`, `SMTP_AUTH_FAILED`, `SMTP_REJECTED`, `SMTP_TEMPORARY_FAILURE`, `SMTP_TIMEOUT`. |
| `reply` | Optional: the server's reply to the failing command (`code`, sanitized `message`); absent when no reply was received (connect, TLS, timeout). Never client-side data. |

Mapping to HTTP, per [email/failures.md](../email/failures.md):

| `failureCode` | HTTP status | `error.message` | `error.smtp` |
| --- | --- | --- | --- |
| `SMTP_CONNECTION_FAILED` | 502 | fixed string | when a reply exists |
| `SMTP_TLS_FAILED` | 502 | fixed string | when a reply exists |
| `SMTP_AUTH_FAILED` | 502 | from the reply category | the reply |
| `SMTP_REJECTED` | 502 | from the reply category | the reply |
| `SMTP_TEMPORARY_FAILURE` | 503 | from the reply category | the reply |
| `SMTP_TIMEOUT` | 504 | fixed string | when a reply exists |

### New configuration keys

| Key (proposal) | Carries |
| --- | --- |
| `SMTP_CONNECT_TIMEOUT` | Seconds to open the connection (and finish implicit TLS). |
| `SMTP_COMMAND_TIMEOUT` | Seconds to wait for each reply after connecting. |
| `SMTP_CA_FILE` | Optional path to a CA bundle used for certificate verification. |

Names, defaults and validation depend on the timeout and TLS items above; when adopted, #152
adds them to [architecture/configuration.md](../architecture/configuration.md). They follow
the duration rule there (positive integers, in seconds).
