# Sending Endpoints

Purpose: define the public sending API (`POST /v1/messages`) and its contract.

## `POST /v1/messages`

Auth: Bearer token with scope `send`.

Request:
```json
{ "to": "user@example.com", "subject": "Hello", "text": "Hi!", "html": "<p>Hi!</p>" }
```

Success `200`:
```json
{ "status": "sent", "smtp": { "code": 250, "message": "OK: queued as ..." } }
```

Failure: error body as in [conventions/errors.md](../conventions/errors.md), codes from
[email/failures.md](../../email/failures.md).

## Pending decisions

None.
