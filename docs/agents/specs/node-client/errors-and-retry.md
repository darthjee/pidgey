# Errors and Retry

Spec of how `pidgey-client` reports failures and decides whether to retry them. The client
itself is in [library.md](library.md).

The API side is described as built in
[api/conventions/errors.md](../../api/conventions/errors.md) (envelope, codes, statuses) and
[email/failures.md](../../email/failures.md) (the `SMTP_*` codes, their statuses and
classification). This spec consumes them unchanged.

## `PidgeyError`

Every failure of `send` (and every configuration mistake) rejects with a single error class,
`PidgeyError`. A result object was rejected: it would hide the difference between a transport
failure and an application failure.

| Field | Content |
| --- | --- |
| `statusCode` | `statusCode` from the envelope body when present, otherwise the HTTP status. Absent when there is no HTTP response (`CLIENT_CONFIGURATION`, `ABORTED`, `NETWORK_ERROR`). |
| `code` | `error.code` from the envelope, or a [client-local code](#client-local-codes). |
| `message` | `error.message` from the envelope (a fixed string for every `SMTP_*` code, see [email/failures.md](../../email/failures.md#fixed-messages)), or a client-written message for a client-local code. |
| `details` | `error.details` when present (validation errors only: one string per failed rule). |
| `smtp` | `error.smtp` when present: `{ code, message }`, the SMTP server's reply to the failing command. |
| `possiblySent` | Boolean: `true` when the message may have been delivered ([Possibly sent](#possibly-sent)). |
| Response body | The original response body, kept on the error for diagnostics. |

Mapping from a response:

1. The body is JSON and carries the envelope (`error` with `code` and `message`): the fields
   above are copied from it.
2. The body is not JSON, or is JSON without `error`: `INVALID_RESPONSE`, keeping the HTTP
   status, instead of a raw parse error.

**Pending decision** (#225): confirm the retained response body can never contain the token or other secrets, and whether it must be bounded or redacted.

## Client-local codes

Transport and configuration failures have no envelope. They get stable codes so that the
classification stays total and consumers never inspect exception types. These codes are
**outside the API's taxonomy**; the documentation states that new client-local codes may be
added (a new code is not a breaking change, see
[packaging.md](packaging.md#what-is-breaking)).

| Code | When | `possiblySent` | Retried |
| --- | --- | --- | --- |
| `CLIENT_CONFIGURATION` | Missing or empty `token`; missing, relative or non-`http(s)` `baseUrl`; negative or non-integer `maxRetries`; invalid `timeoutMs`; `send` argument absent, `null`, an array or a non-object. No request is made. | `false` | never |
| `NETWORK_ERROR` | DNS failure, connection refused, connection reset, TLS failure, client timeout. | see [Ambiguous failures](#ambiguous-failures) | see [Retry table](#retry-table) |
| `INVALID_RESPONSE` | A non-JSON body, or JSON without the envelope, on a non-`200` status; a non-JSON body on a 2xx; any 3xx. Keeps the HTTP status. | `true` on a 2xx, `false` otherwise | never |
| `ABORTED` | The caller's `AbortSignal` fired, before or during an attempt, or during the backoff wait. | `false` | never |

## Ambiguous failures

A failure is **ambiguous** when Pidgey may have delivered the message even though the client
saw an error:

| Failure | Ambiguous | Why |
| --- | --- | --- |
| `NETWORK_ERROR`: DNS failure, connection refused, TLS failure before the request was written | no | The request provably never reached Pidgey. |
| `NETWORK_ERROR`: client timeout, reset after the request was written | yes | Pidgey may have sent the message and the response was lost. |
| `SMTP_TIMEOUT` (`504`) | yes | A timeout after the final `.` does not prove non-delivery ([email/failures.md](../../email/failures.md#classification-rules)). |
| Unknown `SMTP_*` on `504` | yes | A timeout: delivery state unknown. |
| `SMTP_PROTOCOL_ERROR` (`502`) | yes | It may happen after `DATA`. |
| `INVALID_RESPONSE` on a 2xx | yes | The API may have delivered the message before the body broke. |

## Possibly sent

- Every `PidgeyError` carries `possiblySent`: `true` for the ambiguous failures above,
  `false` otherwise.
- When retries are exhausted on a mix of failures, `possiblySent` is `true` if **any**
  attempt was ambiguous, not only the last one.

## Retry policy

- Retries are **on by default** and opt-out: `maxRetries` defaults to `2`, so a call is
  attempted at most three times. `maxRetries: 0` performs exactly one attempt.
- The decision is made from **`error.code`** (Pidgey's code in the envelope), not from the
  HTTP status alone: `502` mixes transient (`SMTP_CONNECTION_FAILED`) and permanent
  (`SMTP_REJECTED`, `SMTP_AUTH_FAILED`, `SMTP_TLS_FAILED`) failures
  ([email/failures.md](../../email/failures.md#codes)). The raw SMTP reply in `error.smtp` is
  never used for the decision.
- When retries are exhausted, the **last** error is the one rejected, so the consumer sees
  the final `SMTP_*` code, not the first attempt's (with `possiblySent` per the "any attempt"
  rule above).
- `/v1` answers no `Retry-After` header (it exists only on the SMTP connection test email,
  [api/conventions/errors.md](../../api/conventions/errors.md#too-many-requests)), so the
  client never reads one.

### Retry table

| Code | HTTP | Retried | Why |
| --- | --- | --- | --- |
| `NETWORK_ERROR`, unambiguous (DNS, refused, TLS before send) | — | yes | The request never reached Pidgey; nothing was sent. |
| `NETWORK_ERROR`, ambiguous (client timeout, reset after send) | — | only with `retryAmbiguous: true` | Pidgey may have sent the message and the response was lost: duplicate risk. |
| `SMTP_CONNECTION_FAILED` | 502 | yes | Could not reach the SMTP server; nothing was sent. |
| `SMTP_TEMPORARY_FAILURE` | 503 | yes | 4xx reply: the server asked to try again later. |
| `SMTP_TIMEOUT` | 504 | only with `retryAmbiguous: true` | A timeout after the final `.` does not prove non-delivery: duplicate risk. |
| `SMTP_REJECTED` | 502 | no | 5xx reply: the recipient, sender or message was refused permanently. |
| `SMTP_AUTH_FAILED` | 502 | no | The connection's credentials must be fixed. |
| `SMTP_TLS_FAILED` | 502 | no | Wrong port or encryption; needs a configuration change. |
| `SMTP_PROTOCOL_ERROR` | 502 | no, even with `retryAmbiguous` | A broken server; it may happen after `DATA`, so a retry risks a duplicate for no likely gain. |
| Unknown `SMTP_*` code | 503 | yes | Forward compatibility: follows the status meaning (temporary). |
| Unknown `SMTP_*` code | 504 | only with `retryAmbiguous: true` | A timeout: delivery state unknown. |
| Unknown `SMTP_*` code | 502 | no | `502` means the upstream rejected the request or is broken. |

**Never retried**, whatever the options: `400` (`VALIDATION_FAILED`, `BAD_REQUEST`), `401`,
`403`, `409` (every code: `NO_SMTP_CONNECTION`, `SMTP_INSECURE_CREDENTIALS`,
`SMTP_HOST_NOT_ALLOWED`, `SMTP_PASSWORD_UNREADABLE`), `429`, `500`, any 3xx, any other
status, any other unknown code, `INVALID_RESPONSE` (including on a 2xx),
`CLIENT_CONFIGURATION` and `ABORTED`. A validation error, a bad token, a missing SMTP
connection or a refused credential will not fix itself, and retrying multiplies the load.
`/v1` does not rate limit today, so a `429` is not expected; one arriving anyway is not
retried.

### `retryAmbiguous`

- **Safe by default.** Failures that may have delivered the message (ambiguous
  `NETWORK_ERROR`, `SMTP_TIMEOUT`, unknown `SMTP_*` on `504`) are **not retried by default**,
  so the default client never causes a duplicate.
- `retryAmbiguous: true` (default `false`) opts in to retrying them, for consumers that prefer
  an occasional duplicate over a lost send. The duplicate risk is documented on the option.
- `SMTP_PROTOCOL_ERROR` and an `INVALID_RESPONSE` on a 2xx are never retried, even with the
  option.
- Removing the client-to-Pidgey duplicate case with an `Idempotency-Key` header is tracked
  separately in #221 (see also [api/pending.md](../../api/pending.md)) and is not part of
  this epic. SMTP has no idempotency, so even a backend key cannot cover `SMTP_TIMEOUT`.

### Backoff

- Exponential, starting at `retryDelayMs` (default `500` ms) and doubling: `500`, `1000`,
  `2000`, … ms, capped at `8000` ms.
- The wait is cancelled by the caller's `AbortSignal` ([library.md](library.md#abort)).

## Forward compatibility

- The success body is passed through unchanged ([library.md](library.md#success)).
- Unknown `SMTP_*` codes follow the status rows of the [Retry table](#retry-table).
- Any other unknown code is surfaced as received and never retried.
