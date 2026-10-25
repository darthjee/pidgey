# Failure Handling

Purpose: define how send failures are detected, categorized and reported.

Every failure is mapped to a stable, upper-case error code returned in the shared API error
envelope (see [api/conventions/errors.md](../api/conventions/errors.md)); the public sending
API uses the same envelope as every other endpoint.

| Code | When | HTTP status |
| --- | --- | --- |
| `VALIDATION_FAILED` | Payload invalid (missing fields, bad address). | 400 |
| `NO_SMTP_CONNECTION` | Organization has no active SMTP connection. | 409 |
| `SMTP_CONNECTION_FAILED` | DNS/TCP connect failure or timeout. | 502 |
| `SMTP_TLS_FAILED` | STARTTLS/SSL negotiation or certificate failure. | 502 |
| `SMTP_AUTH_FAILED` | Server rejected credentials. | 502 |
| `SMTP_REJECTED` | Server rejected sender, recipient or data (5xx reply). | 502 |
| `SMTP_TEMPORARY_FAILURE` | Server returned a 4xx reply. | 503 |
| `SMTP_TIMEOUT` | A command exceeded its timeout after connecting. | 504 |
| `INTERNAL_ERROR` | Unexpected error (real error logged, never sent). | 500 |

- Validation failures list one message per failed rule in `error.details`, following the
  templates in [api/conventions/validation.md](../api/conventions/validation.md).
- SMTP failures include, when available, the SMTP server reply in a dedicated `error.smtp`
  object (`{ "code": 550, "message": "5.1.1 User unknown" }`), matching the `smtp` field of
  the success body, so the client can act on it. `error.details` stays reserved for
  validation messages. Credentials are never included.
- `error.smtp` carries only the server's reply to the failing command, never client-side
  data (e.g. the `AUTH` payload). Its `message` has CR, LF and other control characters
  stripped and its length capped.
- For `SMTP_CONNECTION_FAILED`, `SMTP_TLS_FAILED` and `SMTP_TIMEOUT`, `error.message` is a
  fixed string, never raw exception text:

  | Code | `error.message` |
  | --- | --- |
  | `SMTP_CONNECTION_FAILED` | `Could not connect to the SMTP server` |
  | `SMTP_TLS_FAILED` | `TLS negotiation with the SMTP server failed` |
  | `SMTP_TIMEOUT` | `The SMTP server timed out` |

- Endpoints reachable without login that send email (password recovery, registration)
  never surface these codes or `error.smtp`: they answer their normal, enumeration-safe
  response and log the failure.

**Pending decision:** exact HTTP status mapping (working assumption above).

**Pending decision:** maximum length of `error.smtp.message` after sanitization.

**Pending decision:** exact wording of the fixed messages above (working assumption).
