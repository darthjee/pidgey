# SMTP Connections

Purpose: define the generic SMTP connection settings an organization configures.

Pidgey speaks **generic SMTP** only; there are no provider-specific integrations.
Connections are organization-scoped and managed in the admin UI, through the endpoints of
[api/endpoints/pidgey-admin/smtp-connections.md](../api/endpoints/pidgey-admin/smtp-connections.md)
(list, create, read, update, delete, activate, test email); the API field is `fromAddress`.

| Field | Description |
| --- | --- |
| `name` | Label shown in the UI. |
| `host` | SMTP server hostname. |
| `port` | SMTP port (typically 25, 465, 587, or 1025 for Mailpit). |
| `encryption` | `none` (plain), `tls` (STARTTLS upgrade), `ssl` (implicit TLS). |
| `username`, `password` | Optional; when present, Pidgey authenticates (AUTH LOGIN/PLAIN). |
| `from_address` | Envelope sender and `From:` header for messages sent through this connection. |
| `active` | Marks the connection used for sending. At most one active connection per organization. |

Active connection:
- A new connection always starts **inactive**; it becomes active only through the activate
  endpoint, which deactivates every other connection of the organization in the same locked
  transaction.
- Deleting the active connection is allowed; the organization then has no active
  connection and `POST /v1/messages` answers `409 NO_SMTP_CONNECTION`.
- The test email can use any connection of the organization, active or not.

Security:
- The SMTP password must be recoverable to authenticate, so it is **encrypted at rest**
  with an application secret key (from configuration), not hashed.
- The password is write-only in the API/UI: never returned in responses (nor logged). The
  API exposes only `hasPassword`, a boolean saying whether one is stored.
- `username` and `password` are both set or both empty.

**Pending decisions:**
- Encryption mechanism for the stored password (e.g. `sodium_crypto_secretbox`).
- Whether `POST /v1/messages` requests may select a non-active connection explicitly (the
  test email already does, see the endpoints above).
- A `deactivate` endpoint and a database-level "at most one active" constraint (see
  [api/endpoints/pidgey-admin/smtp-connections.md](../api/endpoints/pidgey-admin/smtp-connections.md#pending-decisions)).
- A host deny-list / allow-list against server-side request forgery (see
  [api/pending.md](../api/pending.md)).
- TLS certificate verification policy (working assumption: always verify).
