# Message Scope

Purpose: define which message features are supported in v1 and which are deferred.

## v1

| Field | Required | Notes |
| --- | --- | --- |
| `to` | yes | A single recipient address. |
| `subject` | yes | Plain text, UTF-8 (encoded as needed in headers). |
| `text` | one of `text`/`html` | Plain-text body. |
| `html` | one of `text`/`html` | HTML body. When both are given, a `multipart/alternative` message is built. |

The `From:` header comes from the SMTP connection's `from_address`.

## Pending decisions (future scope)

- Multiple recipients, `cc`, `bcc`, and whether `to` accepts an array.
- `reply_to` and custom headers.
- Overriding `from` per request.
- **Attachments.**
- **Templates** (stored templates with variables).
