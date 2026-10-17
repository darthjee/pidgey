# Failure Handling

Purpose: define how send failures are detected, categorized and reported.

Every failure is mapped to a stable error code returned in the API error body
(see [api/conventions.md](../api/conventions.md)).

| Code | When | HTTP status |
| --- | --- | --- |
| `validation_error` | Payload invalid (missing fields, bad address). | 422 |
| `no_smtp_connection` | Organization has no active SMTP connection. | 409 |
| `smtp_connection_failed` | DNS/TCP connect failure or timeout. | 502 |
| `smtp_tls_failed` | STARTTLS/SSL negotiation or certificate failure. | 502 |
| `smtp_auth_failed` | Server rejected credentials. | 502 |
| `smtp_rejected` | Server rejected sender, recipient or data (5xx reply). | 502 |
| `smtp_temporary_failure` | Server returned a 4xx reply. | 503 |
| `smtp_timeout` | A command exceeded its timeout after connecting. | 504 |
| `internal_error` | Unexpected error. | 500 |

SMTP failures include, when available, the SMTP reply code and message in the error
`details` so the client can act on them. Credentials are never included.

**Pending decision:** exact HTTP status mapping (working assumption above).
