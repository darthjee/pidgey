# Errors

Purpose: define the single error envelope, the error codes and the HTTP statuses used by
every API endpoint, including the public sending API.

## Envelope

Every error, whatever produced it and on whichever endpoint (admin, auth, operational or
`POST /v1/messages`), has the same shape; the backend has a single error formatter.
The readiness probe's `503` is not an error in this sense: it answers its own probe body (see [endpoints/operational.md](../endpoints/operational.md#get-readyjson)).

```json
{
  "error": {
    "code": "UNAUTHORIZED",
    "message": "Invalid or expired access token"
  },
  "statusCode": 401,
  "timestamp": "2026-10-25T12:00:00.000Z"
}
```

| Field | Presence | Content |
| --- | --- | --- |
| `error.code` | always | Upper-case code (see [Codes](#codes)). |
| `error.message` | always | Human-readable message. |
| `error.details` | validation errors only | Array of strings, one per failed rule ([validation.md](validation.md)). |
| `error.smtp` | SMTP send failures only, when a reply is available | `{ "code": <int>, "message": <string> }`, the SMTP server reply. |
| `statusCode` | always | The HTTP status, repeated in the body. |
| `timestamp` | always | ISO 8601 UTC with milliseconds. |

### Validation errors

`details` carries one entry per failed rule, and `message` is those entries joined by `"; "`:

```json
{
  "error": {
    "code": "VALIDATION_FAILED",
    "message": "email must be an email; password must be longer than or equal to 8 characters",
    "details": ["email must be an email", "password must be longer than or equal to 8 characters"]
  },
  "statusCode": 400,
  "timestamp": "2026-10-25T12:00:00.000Z"
}
```

### SMTP send failures

`smtp` mirrors the `smtp` field of the `POST /v1/messages` success body; `details` stays
reserved for validation messages. Credentials are **never** included.

- `error.smtp` contains **only the server's reply to the failing command**, never
  client-side data (e.g. the `AUTH` payload, the message content or the envelope sent).
- `error.smtp.message` is sanitized: CR, LF and other control characters are stripped and
  its length is capped.
- For `SMTP_CONNECTION_FAILED`, `SMTP_TLS_FAILED` and `SMTP_TIMEOUT`, `error.message` is a
  fixed string defined in [email/failures.md](../../email/failures.md), never raw exception
  text.
- Endpoints reachable without login that send email (password recovery, registration)
  never surface `SMTP_*` codes or `error.smtp`: they answer their normal, enumeration-safe
  response and log the failure (see [Enumeration safety](#enumeration-safety)).

```json
{
  "error": {
    "code": "SMTP_REJECTED",
    "message": "Recipient rejected",
    "smtp": { "code": 550, "message": "5.1.1 User unknown" }
  },
  "statusCode": 502,
  "timestamp": "2026-10-25T12:00:00.000Z"
}
```

## Codes

`error.code` is the **specific code** when the endpoint sets one, otherwise the **category
code** derived from the status.

### Category codes

| Status | Code |
| --- | --- |
| 400 | `BAD_REQUEST` (or `VALIDATION_FAILED` for body validation) |
| 401 | `UNAUTHORIZED` |
| 403 | `FORBIDDEN` |
| 404 | `NOT_FOUND` |
| 409 | `CONFLICT` |
| 423 | `LOCKED` |
| 429 | `TOO_MANY_REQUESTS` (reserved, not currently emitted) |
| 500 | `INTERNAL_ERROR` |
| other | `HTTP_<status>`, e.g. `HTTP_422` |

### Specific codes

This table is the registry of specific codes; endpoint specs that add a code must add it
here.

| Code | Status | Message | Where |
| --- | --- | --- | --- |
| `USERNAME_TAKEN` | 409 | `username is not available` (register) / `Username already in use` (account & admin edit) | register, account, admin edit |
| `EMAIL_TAKEN` | 409 | `email is not available` (register) / `Email already in use` (account & admin edit) | register, account, admin edit |
| `USER_DENIED` | 403 | `This account has been denied access` | login |
| `USER_NOT_PENDING` | 422 | `Only pending users can be approved` | admin approve |
| `CANNOT_DENY_SELF` | 422 | `You cannot deny your own account` | admin deny |
| `NO_SMTP_CONNECTION`, `SMTP_CONNECTION_FAILED`, `SMTP_TLS_FAILED`, `SMTP_AUTH_FAILED`, `SMTP_REJECTED`, `SMTP_TEMPORARY_FAILURE`, `SMTP_TIMEOUT` | 409 / 502 / 503 / 504 | See [email/failures.md](../../email/failures.md). | `POST /v1/messages` and authenticated endpoints that send email (the SMTP connection test email, which never answers `NO_SMTP_CONNECTION`); never endpoints reachable without login (see [SMTP send failures](#smtp-send-failures)) |

## Generic errors

- **Unexpected errors** (bugs, database down…) answer `500` with
  `{ "code": "INTERNAL_ERROR", "message": "Internal server error" }`. The real error is
  logged, never sent.
- **Body-parser failures** (malformed JSON, body too large, unsupported content type or
  charset) answer their own status (`400`, `413`, `415`) with the category code and the
  standard reason phrase, e.g. `{ "code": "BAD_REQUEST", "message": "Bad Request" }`.
- **Unknown route** answers `404` with code `NOT_FOUND` (message like
  `Cannot POST /foo.json`).

## Status codes

| Status | Use |
| --- | --- |
| 200 / 201 / 204 | Success (see [format.md](format.md#success-codes)). |
| 400 | Malformed body (`BAD_REQUEST`) or validation failure (`VALIDATION_FAILED`). |
| 401 | Missing, invalid, expired or revoked credentials — nothing else. |
| 403 | Authenticated but not allowed (missing scope, not an admin, denied user), or cross-site request rejected by the [origin check](origin-check.md). |
| 404 | Not found, including resources of other organizations and unknown routes. |
| 409 | State conflict (e.g. username/email taken, no active SMTP connection). |
| 413 | Body too large. |
| 415 | Unsupported content type. |
| 422 | Business rule violated on a valid request (e.g. `USER_NOT_PENDING`); never used for body validation. |
| 423 | Locked (e.g. account edit lockout). |
| 500 | Unexpected internal error. |
| 502 / 503 / 504 | SMTP upstream failures (see [email/failures.md](../../email/failures.md)). |

## Never `401` for a business rule

The admin frontend's API client reacts to **`401` only** by refreshing the session and
retrying once. A `401` must therefore mean "your credentials are missing, invalid or expired"
and nothing else. Every business rejection uses `400`, `403`, `409`, `422` or `423` as listed
in the endpoint specs, **never `401`**.

## Enumeration safety

Error responses must not reveal whether an account, user or resource exists when the caller
is not entitled to know it: unknown and unauthorized cases answer the same status, code and
message (e.g. resources of other organizations answer `404`, as if they did not exist). The
concrete rules (login, password recovery, device authorization…) live in the endpoint specs.
**Register is the documented exception:** its `USERNAME_TAKEN` / `EMAIL_TAKEN` answers reveal
that a username or email exists (see
[endpoints/auth/session.md](../endpoints/auth/session.md#known-limitation-enumeration)).
SMTP failures on endpoints reachable without login are not surfaced either (see
[SMTP send failures](#smtp-send-failures)).

**Pending decision:** maximum length of `error.smtp.message` after sanitization.
