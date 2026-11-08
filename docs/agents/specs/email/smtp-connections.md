# SMTP Connections

Purpose: define the generic SMTP connection settings an organization configures.

Pidgey speaks **generic SMTP** only; there are no provider-specific integrations.
Connections are organization-scoped and managed in the admin UI, through the endpoints of
[api/endpoints/pidgey-admin/smtp-connections.md](../api/endpoints/pidgey-admin/smtp-connections.md)
(list, create, read, update, delete, activate, test email); the API field is `fromAddress`.

| Field | Description |
| --- | --- |
| `name` | Label shown in the UI. |
| `host` | SMTP server hostname (ASCII LDH, an IDN in punycode) or IP literal: the connect target and the TLS peer name, never the `EHLO` argument. |
| `port` | SMTP port (typically 25, 465, 587, or 1025 for Mailpit). |
| `encryption` | `none` (plain), `tls` (STARTTLS upgrade), `ssl` (implicit TLS). |
| `username`, `password` | Optional; when present, Pidgey authenticates (see [Authentication](#authentication)). Require `encryption` `tls` or `ssl`. |
| `from_address` | Envelope sender and `From:` header for messages sent through this connection. |
| `active` | Marks the connection used for sending. At most one active connection per organization. |

## Active connection

- A new connection always starts **inactive**; it becomes active only through the activate
  endpoint, which deactivates every other connection of the organization in the same locked
  transaction.
- Deleting the active connection is allowed; the organization then has no active
  connection and `POST /v1/messages` answers `409 NO_SMTP_CONNECTION`.
- The test email can use any connection of the organization, active or not.

## Authentication

- Credentials are present when `username` and `password` are set (always both or neither).
- Supported mechanisms: `PLAIN` (preferred, sent as an initial response) and `LOGIN`
  (fallback), read from the last `EHLO` (post-TLS for `tls`). `CRAM-MD5`, `XOAUTH2`,
  `OAUTHBEARER` and any other mechanism are out of scope for v1. The step-by-step rules and
  failure mapping are in [send-flow.md](send-flow.md#auth).
- **Credentials require encryption:** they are only allowed with `encryption` `tls` or
  `ssl`; a password is never sent in clear text.
  - Create and update reject credentials with `encryption: none` (on the merged state for
    update): `400 VALIDATION_FAILED`, `username and password require encryption tls or ssl`
    (see
    [api/endpoints/pidgey-admin/smtp-connections.md](../api/endpoints/pidgey-admin/smtp-connections.md#field-rules)).
  - At send time (test email and `POST /v1/messages`), a stored row with credentials and
    `encryption: none` answers `409 SMTP_INSECURE_CREDENTIALS` before any connection and
    before the password is decrypted (see [send-flow.md](send-flow.md#send-time-checks)).
    No data migration: such a row keeps being listed, shown, activated and deleted, but
    every update fails with `400` until `encryption` or the credentials are fixed.
- No downgrade: `tls` never falls back to plain text when `STARTTLS` is missing or fails
  (see [send-flow.md](send-flow.md#starttls-encryption-tls)).

## TLS policy

Applies to `ssl` and to `tls` after `STARTTLS`.

- **Always verify:** `verify_peer`, `verify_peer_name`, no self-signed certificates. No
  per-connection opt-out, no "skip verification" flag, no pinning.
- **Peer name = the connection's `host` as stored.**
  - Hostname: SNI is sent with `host`; name matching per PHP / OpenSSL (SAN `dNSName`,
    wildcard only in the left-most label).
  - IP literal: must match an IP SAN, no CN fallback; no SNI (RFC 6066).
- **Minimum TLS 1.2** (TLS 1.2 and 1.3 only; TLS 1.0 / 1.1 refused, RFC 8996). Ciphers are
  the OpenSSL defaults. Legacy TLS is not allowed: weak crypto, OpenSSL 3 would need
  `@SECLEVEL=0` (weakening every handshake), not controllable on the shared host,
  untestable in CI, negligible benefit.
- **CA bundle:** the system store by default (`openssl.cafile` / `openssl.capath` / the
  OpenSSL default). The optional `SMTP_CA_FILE` setting (see
  [architecture/configuration.md](../architecture/configuration.md#smtp-transport))
  **replaces** the system store with a PEM bundle (to trust both, concatenate them); test
  and CI set it to the test CA.
- Any handshake, verification or protocol-version failure → `SMTP_TLS_FAILED` (fixed
  message, see [failures.md](failures.md#fixed-messages)). The OpenSSL reason (e.g.
  `certificate verify failed`) goes to the attempt log only, never the response.
- Future, not v1: a per-connection custom CA (covered globally by `SMTP_CA_FILE`) and a
  per-connection "legacy TLS" opt-in.

## Security
- The SMTP password must be recoverable to authenticate, so it is **encrypted at rest**
  with an application secret key (from configuration), not hashed.
- The password is write-only in the API/UI: never returned in responses (nor logged). The
  API exposes only `hasPassword`, a boolean saying whether one is stored.
- `username` and `password` are both set or both empty.
- The encryption mechanism, keys, rotation and decrypt failures are defined in
  [password-encryption.md](password-encryption.md).

## Pending decisions

- Whether `POST /v1/messages` requests may select a non-active connection explicitly (the
  test email already does, see the endpoints above).
- A `deactivate` endpoint and a database-level "at most one active" constraint (see
  [api/endpoints/pidgey-admin/smtp-connections.md](../api/endpoints/pidgey-admin/smtp-connections.md#pending-decisions)).
- A host deny-list / allow-list against server-side request forgery (see
  [api/pending.md](../api/pending.md)).
