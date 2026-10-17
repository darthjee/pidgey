# SMTP Connections

Purpose: define the generic SMTP connection settings an organization configures.

Pidgey speaks **generic SMTP** only; there are no provider-specific integrations.
Connections are organization-scoped and managed in the admin UI.

| Field | Description |
| --- | --- |
| `name` | Label shown in the UI. |
| `host` | SMTP server hostname. |
| `port` | SMTP port (typically 25, 465, 587, or 1025 for Mailpit). |
| `encryption` | `none` (plain), `tls` (STARTTLS upgrade), `ssl` (implicit TLS). |
| `username`, `password` | Optional; when present, Pidgey authenticates (AUTH LOGIN/PLAIN). |
| `from_address` | Envelope sender and `From:` header for messages sent through this connection. |
| `active` | Marks the connection used for sending. At most one active connection per organization. |

Security:
- The SMTP password must be recoverable to authenticate, so it is **encrypted at rest**
  with an application secret key (from configuration), not hashed.
- The password is write-only in the API/UI: never returned in responses.

**Pending decisions:**
- Encryption mechanism for the stored password (e.g. `sodium_crypto_secretbox`).
- Whether requests may select a non-active connection explicitly.
- TLS certificate verification policy (working assumption: always verify).
