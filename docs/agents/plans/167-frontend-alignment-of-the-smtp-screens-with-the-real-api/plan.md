# Plan: Frontend alignment of the SMTP screens with the real API

Issue: [167-frontend-alignment-of-the-smtp-screens-with-the-real-api.md](../../issues/167-frontend-alignment-of-the-smtp-screens-with-the-real-api.md)

## Overview

This plan closes the M7–M9 mismatches of `api-compatibility.md`:

- **M7:** `SmtpConnectionsClient.deactivate(id)`, and a Deactivate action on the active row with
  confirmation and a list refresh.
- **M8:** two client-side rules in `SmtpConnectionFormRules`: credentials require encryption,
  and credential redirection on edit.
- **M9:** the test-email modal shows `429`, wraps the SMTP reply and gets the new recipient help
  text.

It also moves the SMTP Jasmine specs onto shared JSON fixtures. Each fixture copies an example
response that product-owner adds to the API spec in the same PR.

The manual end-to-end check is not run by the agents. It goes into the PR description as a
pre-merge checklist, which the owner runs once #166 is merged. Until then, item 7 of
`implement-email/frontend.md` stays unticked.

## Agents involved

- [product-owner](product-owner.md): example responses in the API spec, the fixture list in
  `frontend/testing.md`, `api-compatibility.md` and `implement-email/frontend.md`.
- [frontend](frontend.md): the client, list, form rules, test modal and fixtures.

## Shared contracts

### Server messages (reused verbatim by the client-side rules)

- `ENCRYPTION_REQUIRED_MESSAGE = 'username and password require encryption tls or ssl'`
- `REDIRECTION_MESSAGE = 'password must be sent again when host, port or encryption changes'`
- Both are shown on the `password` field, and both are also mapped there by
  `mapValidationDetails` when the server answers them. Neither starts with a field name, so
  without that mapping they would end up as unmatched details.

### Deactivate

- `SmtpConnectionsClient.deactivate(id)` sends `POST /smtp-connections/:id/deactivate.json` with
  no body. It resolves to `{ smtpConnection }`, or `undefined` when the session expired.
- `DEACTIVATE_CONFIRM_MESSAGE = 'Deactivate the active SMTP connection? Sending stops (POST /v1/messages answers 409 NO_SMTP_CONNECTION) until another connection is activated.'`

### Example responses and fixtures

The API spec (`api/endpoints/pidgey-admin/smtp-connections.md`) gains one example per shape
below. Each fixture under `frontend/specs/support/fixtures/smtpConnections/` is a byte-equal copy
of the example's JSON. Both agents use exactly these bodies:

| Fixture file | Example location in `smtp-connections.md` | Body |
| --- | --- | --- |
| `item.json` | `## SMTP connection item` (already present) | `{ "smtpConnection": <the existing item example> }` |
| `list.json` | `## GET /smtp-connections.json` (new) | `{ "smtpConnections": [<item id 12, active>, <id 13, below>] }` |
| `testSent.json` | `## POST /smtp-connections/:id/test.json` (already present) | the existing `{ "status": "sent", "smtp": … }` |
| `validationFailed.json` | `## Field rules` (new) | 400 envelope, below |
| `smtpFailure.json` | test-email section (new) | 502 envelope, below |
| `sendTimeFailure.json` | test-email section (new) | 409 envelope, below |
| `tooManyRequests.json` | `### Test-email limit` (new) | 429 envelope, below |
| `notFound.json` | `## Errors summary` (new) | 404 envelope, below |

`item.json` wraps the existing item exactly as `GET /smtp-connections/:id.json` answers it. The
second list entry:

```json
{
  "id": 13,
  "name": "Office relay",
  "host": "smtp.example.com",
  "port": 587,
  "encryption": "tls",
  "username": "relay-user",
  "hasPassword": true,
  "fromAddress": "noreply@example.com",
  "active": false,
  "createdAt": "2026-10-18T08:15:00.000Z",
  "updatedAt": "2026-10-18T08:15:00.000Z"
}
```

The error bodies, all with `"timestamp": "2026-10-25T12:00:00.000Z"`:

- 400:
  `{ "error": { "code": "VALIDATION_FAILED", "message": "port must be between 1 and 65535; username and password require encryption tls or ssl", "details": ["port must be between 1 and 65535", "username and password require encryption tls or ssl"] }, "statusCode": 400, … }`
- 502:
  `{ "error": { "code": "SMTP_AUTH_FAILED", "message": "The SMTP server rejected the credentials", "smtp": { "code": 535, "message": "5.7.8 Authentication credentials invalid" } }, "statusCode": 502, … }`
- 409:
  `{ "error": { "code": "SMTP_INSECURE_CREDENTIALS", "message": "The SMTP connection has credentials but no encryption; set encryption to tls or ssl" }, "statusCode": 409, … }`
- 429:
  `{ "error": { "code": "TOO_MANY_REQUESTS", "message": "Too many test emails; try again later" }, "statusCode": 429, … }`
- 404:
  `{ "error": { "code": "NOT_FOUND", "message": "SMTP connection not found" }, "statusCode": 404, … }`

### Fixture loader

`frontend/specs/support/loadFixture.js` exports `loadFixture(name)`. It reads
`specs/support/fixtures/<name>.json` (for example `loadFixture('smtpConnections/list')`) and
returns a fresh parsed copy, so specs can mutate it. Specs that need an `ApiError` build it from
the fixture: `new ApiError(body.statusCode, body.error.message, body.error.code, body.error.details, body.error.smtp)`.
