# Conventions

Purpose: define the shared rules for all API endpoints.

- Requests and responses are JSON (`Content-Type: application/json; charset=utf-8`).
  Requests with another content type on body-bearing endpoints → `415`.
- Field names in `snake_case`.
- Validation happens before any side effect; all field errors are reported together.

## Error body

```json
{
  "error": {
    "code": "validation_error",
    "message": "Human readable summary",
    "details": { "to": ["must be a valid email address"] }
  }
}
```

## Status codes

| Status | Use |
| --- | --- |
| 200 / 201 / 204 | Success / created / no content. |
| 400 | Malformed JSON. |
| 401 | Not authenticated. |
| 403 | Authenticated but missing scope. |
| 404 | Not found (including resources of other organizations). |
| 409 | State conflict (e.g. no active SMTP connection). |
| 415 | Unsupported content type. |
| 422 | Validation error. |
| 5xx | Upstream SMTP or internal failures (see [email/failures.md](../email/failures.md)). |
