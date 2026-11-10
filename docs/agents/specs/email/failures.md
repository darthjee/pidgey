# Failure Handling

Purpose: define how send failures are detected, categorized and reported.

Every failure is mapped to a stable, upper-case error code returned in the shared API error
envelope (see [api/conventions/errors.md](../api/conventions/errors.md)); the public sending
API uses the same envelope as every other endpoint.

## Codes

| Code | When | HTTP status |
| --- | --- | --- |
| `VALIDATION_FAILED` | Payload invalid (missing fields, bad address). | 400 |
| `NO_SMTP_CONNECTION` | Organization has no active SMTP connection. | 409 |
| `SMTP_INSECURE_CREDENTIALS` | The connection has credentials but `encryption: none`; checked before the password is decrypted, the server is never contacted. | 409 |
| `SMTP_HOST_NOT_ALLOWED` | The connection's `host` resolves to (or is) an address in a denied range while `SMTP_ALLOW_PRIVATE_HOSTS` is `false` (see [send-flow.md](send-flow.md#host-check)); the server is never contacted. | 409 |
| `SMTP_PASSWORD_UNREADABLE` | The stored SMTP password could not be decrypted (wrong or removed key, corrupt bytes, associated-data mismatch); the server is never contacted. | 409 |
| `SMTP_CONNECTION_FAILED` | DNS / TCP connect failure, a timeout before TCP is established, or the peer closing the connection without sending any greeting. | 502 |
| `SMTP_TLS_FAILED` | `tls` without `STARTTLS` in the `EHLO` reply, `STARTTLS` not answered `220`, bytes received between `220` and the handshake, or any handshake, certificate verification or protocol-version failure (`ssl` and `tls`). | 502 |
| `SMTP_AUTH_FAILED` | The server rejected the credentials, offers no supported mechanism while credentials are set, or answered `530` to `MAIL FROM` without credentials. | 502 |
| `SMTP_REJECTED` | The server rejected the connection (greeting `554`, `EHLO`), the sender, a recipient or the message (5xx reply). | 502 |
| `SMTP_TEMPORARY_FAILURE` | The server returned a 4xx reply (greeting `421` included). | 503 |
| `SMTP_PROTOCOL_ERROR` | The server sent a malformed reply, or closed the connection with no reply after the greeting (see [send-flow.md](send-flow.md#replies)). | 502 |
| `SMTP_TIMEOUT` | Any timeout after TCP is established (handshake, greeting, any reply, the session deadline). | 504 |
| `INTERNAL_ERROR` | Unexpected error (real error logged, never sent). | 500 |

- Validation failures list one message per failed rule in `error.details`, following the
  templates in [api/conventions/validation.md](../api/conventions/validation.md).
- HTTP mapping: `502` the upstream rejected the request or is broken, `503` temporary,
  `504` timeout, `409` the connection must be fixed. No `Retry-After` header on `503`.

## Fixed messages

`error.message` is always a **fixed string**, never server text and never raw exception
text; server text lives only in `error.smtp.message`. The strings are API contract and are
asserted exactly by tests.

| Code | HTTP | `error.message` | `error.smtp` |
| --- | --- | --- | --- |
| `SMTP_INSECURE_CREDENTIALS` | 409 | `The SMTP connection has credentials but no encryption; set encryption to tls or ssl` | never |
| `SMTP_HOST_NOT_ALLOWED` | 409 | `The SMTP host resolves to an address that is not allowed` | never |
| `SMTP_PASSWORD_UNREADABLE` | 409 | `The stored SMTP password could not be decrypted; set it again` | never |
| `SMTP_CONNECTION_FAILED` | 502 | `Could not connect to the SMTP server` | never |
| `SMTP_TLS_FAILED` | 502 | `TLS negotiation with the SMTP server failed` (+ [hint](#port--encryption-hints)) | the `STARTTLS` reply, if any |
| `SMTP_AUTH_FAILED` | 502 | see [`SMTP_AUTH_FAILED` variants](#smtp_auth_failed-variants) | the reply, if any |
| `SMTP_REJECTED` | 502 | see [`SMTP_REJECTED` by step](#smtp_rejected-by-step) | the reply |
| `SMTP_TEMPORARY_FAILURE` | 503 | `The SMTP server is temporarily unavailable; try again later` | the reply |
| `SMTP_PROTOCOL_ERROR` | 502 | `The SMTP server sent an invalid reply` | never |
| `SMTP_TIMEOUT` | 504 | `The SMTP server timed out` (+ [hint](#port--encryption-hints)) | never |

### `SMTP_AUTH_FAILED` variants

| Situation | `error.message` | `error.smtp` |
| --- | --- | --- |
| Any reply other than `235` to `AUTH` or to a `334` step | `The SMTP server rejected the credentials` | the reply |
| Credentials set; the final `EHLO` advertises no `AUTH`, or neither `PLAIN` nor `LOGIN` | `The SMTP server offers no supported authentication mechanism` | absent |
| No credentials; `MAIL FROM` answered `530` | `The SMTP server requires authentication` | the reply |

### `SMTP_REJECTED` by step

| Failing step | `error.message` |
| --- | --- |
| Greeting (`554`) or `EHLO` | `The SMTP server rejected the connection` |
| `MAIL FROM` | `The SMTP server rejected the sender` |
| `RCPT TO` | `The SMTP server rejected the recipient` |
| `DATA` or the final `.` | `The SMTP server rejected the message` |

### Port / encryption hints

When an `SMTP_TIMEOUT` or `SMTP_TLS_FAILED` happens on a well-known mismatched port /
encryption pair, a hint is appended to the fixed message. The pairs are fixed; non-standard
ports never get a hint, and no create / update validation rejects these pairs (see
[send-flow.md](send-flow.md#port--encryption-mismatch)).

| Pair | `error.message` |
| --- | --- |
| port 465 with `tls` or `none`, timeout | `The SMTP server timed out; port 465 usually requires encryption ssl` |
| port 587 with `ssl`, TLS failure | `TLS negotiation with the SMTP server failed; port 587 usually uses encryption tls` |
| port 25 with `ssl`, TLS failure | `TLS negotiation with the SMTP server failed; port 25 usually uses encryption tls` |

## Classification rules

- **The step wins over the reply class.** Any non-`220` reply to `STARTTLS` (`454`, `5xx`,
  …) is `SMTP_TLS_FAILED`; any non-`235` reply to `AUTH` or to a `334` step (`535`, `534`,
  `538`, `454`, …) is `SMTP_AUTH_FAILED` — never `SMTP_TEMPORARY_FAILURE` or
  `SMTP_REJECTED`.
- **`530` at `MAIL FROM` without credentials** is `SMTP_AUTH_FAILED`, not `SMTP_REJECTED`.
  This includes `530 5.7.0 Must issue a STARTTLS command first` on `none`; there is no
  text-based classification.
- **Other replies:** a 5xx reply is `SMTP_REJECTED`, a 4xx reply is
  `SMTP_TEMPORARY_FAILURE`. A greeting other than `220`: `554` → `SMTP_REJECTED`, `421` or
  any other 4xx → `SMTP_TEMPORARY_FAILURE`.
- **Timeouts** (see [send-flow.md](send-flow.md#timeouts)): a timeout before TCP is
  established is `SMTP_CONNECTION_FAILED`; any timeout after it (the `ssl` or `STARTTLS`
  handshake, the greeting, any reply, the session deadline) is `SMTP_TIMEOUT`. An
  `SMTP_TIMEOUT` after the final `.` does not prove the message was not delivered.
- **Close with no reply:** the peer closing the connection before sending any byte of the
  expected reply (not mid-reply, which is malformed, see
  [send-flow.md](send-flow.md#replies)): at the greeting → `SMTP_CONNECTION_FAILED`; at any
  later step → `SMTP_PROTOCOL_ERROR`; during `QUIT` → ignored.
- **TLS:** the OpenSSL reason (e.g. `certificate verify failed`) goes to the log line only,
  never the response.
- **`QUIT`** is best-effort: its reply or failure never turns a sent message into a
  failure.

## `error.smtp`

- SMTP failures include, when available, the SMTP server reply in a dedicated `error.smtp`
  object (`{ "code": 550, "message": "5.1.1 User unknown" }`), matching the `smtp` field of
  the success body, so the client can act on it. `error.details` stays reserved for
  validation messages. Credentials are never included.
- `error.smtp` carries only the server's reply to the failing command, never client-side
  data (e.g. the `AUTH` payload).
- Its `message` is the reply text (see [send-flow.md](send-flow.md#reply-text)), then
  redacted and sanitized as below. The same processing applies to the success
  `smtp.message`.

### Echo redaction

Before a reply text is used (`error.smtp`, log), any occurrence of the password or of the
base64 `AUTH` payload is replaced with `[redacted]`.

### Sanitization and length cap

Applied to the joined reply text:

1. Keep printable ASCII (`0x20`–`0x7E`); every other byte becomes `?` (replies are untyped
   bytes; invalid UTF-8 would break `json_encode`).
2. Tabs become spaces, runs of spaces collapse to one, then trim.
3. Cap at **512 characters**: a longer text is cut to 509 characters plus `...`.

## Scope

- `SMTP_INSECURE_CREDENTIALS`, `SMTP_HOST_NOT_ALLOWED` and `SMTP_PASSWORD_UNREADABLE` apply
  to the test email and `POST /v1/messages`, before any connection is opened, in that order
  (see [send-flow.md](send-flow.md#send-time-checks)). The credentials check and the host
  check run before the password is decrypted; every decrypt failure (see
  [password-encryption.md](password-encryption.md#read-path)) gets
  `SMTP_PASSWORD_UNREADABLE`. A host that cannot be resolved stays
  `SMTP_CONNECTION_FAILED`.
- Endpoints reachable without login that send email (password recovery, registration)
  never surface these codes or `error.smtp`: they answer their normal, enumeration-safe
  response and log the failure.
