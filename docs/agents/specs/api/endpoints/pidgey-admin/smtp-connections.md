# SMTP Connection Endpoints

Purpose: define the `/smtp-connections*.json` endpoints: list, create, read, update, delete,
activate and send a test email through an organization's SMTP connections.

Shared rules (access, guards, `:id` handling, field rules, operator log) are in
[pidgey-admin.md](../pidgey-admin.md). The meaning of each setting is in
[email/smtp-connections.md](../../../email/smtp-connections.md), the password encryption in
[email/password-encryption.md](../../../email/password-encryption.md); the table in
[organizations/data-model/smtp-connections.md](../../../organizations/data-model/smtp-connections.md).
Errors use [conventions/errors.md](../../conventions/errors.md).

| Method & path | Success |
| --- | --- |
| `GET /smtp-connections.json` | `200` |
| `POST /smtp-connections.json` | `201` |
| `GET /smtp-connections/:id.json` | `200` |
| `PATCH /smtp-connections/:id.json` | `200` |
| `DELETE /smtp-connections/:id.json` | `204` |
| `POST /smtp-connections/:id/activate.json` | `200` |
| `POST /smtp-connections/:id/test.json` | `201` |

An unknown id, an id out of range, or another organization's connection answers
`404 NOT_FOUND` — `SMTP connection not found` on every `:id` route (see
[`:id` path parameter](../pidgey-admin.md#id-path-parameter)).

## SMTP connection item

```json
{
  "id": 12,
  "name": "Mailpit",
  "host": "mailpit",
  "port": 1025,
  "encryption": "none",
  "username": null,
  "hasPassword": false,
  "fromAddress": "noreply@example.com",
  "active": true,
  "createdAt": "2026-10-17T12:00:00.000Z",
  "updatedAt": "2026-10-20T09:30:00.000Z"
}
```

| Field | Content |
| --- | --- |
| `id` | `smtp_connections.id`. |
| `name`, `host`, `port`, `encryption` | Stored values. |
| `username` | Stored SMTP username, or `null` when none is set. |
| `hasPassword` | `true` when a password is stored (`password IS NOT NULL`), otherwise `false`. It stays `true` when the stored password cannot be decrypted (list and show never decrypt). |
| `fromAddress` | `smtp_connections.from_address`. |
| `active` | Whether this is the organization's active connection. |
| `createdAt`, `updatedAt` | ISO 8601 UTC with milliseconds. |

- **`password` is write-only:** it is never returned, in any response (item, list, error or
  test email), and never logged. `hasPassword` is the only hint that one is stored.
- The stored password is encrypted at rest, as defined in
  [email/password-encryption.md](../../../email/password-encryption.md).
- `organizationId` is not exposed: it is always the caller's organization.

## Field rules

Used by create and update. Strings are trimmed before validation, except `password`, which is
**never trimmed**.

| Field | Rules |
| --- | --- |
| `name` | **required**; string, not empty, max 255 characters |
| `host` | **required**; string, not empty, max 255 characters; an ASCII hostname or an IP literal (IPv4, or IPv6 in brackets or bare); no whitespace or control characters. A hostname is LDH labels (letters, digits, hyphen; 1–63 characters each, no leading or trailing hyphen) separated by dots, at most 253 characters in total; an IDN must be entered in punycode (`smtp.xn--exmple-cua.de`), a Unicode host is rejected (no conversion). A bare IPv6 literal is stored as given and bracketed when the connect target is built. `host` is the connect target and the TLS peer name, never the `EHLO` argument |
| `port` | **required**; integer (JSON number, no fraction, not a string), 1–65535 |
| `encryption` | **required**; one of `none`, `tls`, `ssl` |
| `username` | optional; string, max 255 characters, no control characters |
| `password` | optional; string, max 255 characters, no CR, LF or NUL |
| `fromAddress` | **required**; valid email address, max 255 characters, no CR or LF |

- **Header injection:** `host` is the connect target and TLS peer name, `fromAddress` ends
  up in SMTP commands and headers (`MAIL FROM`, `From:`, the `Message-ID` domain), and
  `username` / `password` in the `AUTH` exchange; the character rules above (and the LDH
  rule of `host`) exist so no value can inject an extra command or header line. The `EHLO`
  argument is the client's name, never `host` (see
  [email/send-flow.md](../../../email/send-flow.md#ehlo)).
- **Credential pairing:** `username` and `password` are **both set or both empty**. The rule
  is checked on the **resulting state** (on update, after the request is merged with the
  stored row); otherwise `400 VALIDATION_FAILED` —
  `username and password must be both set or both empty`.
- **Credentials require encryption:** credentials are only allowed with `encryption` `tls`
  or `ssl`, checked on the **resulting state** as well; otherwise `400 VALIDATION_FAILED` —
  `username and password require encryption tls or ssl` (a password is never sent in clear
  text, see [email/smtp-connections.md](../../../email/smtp-connections.md#authentication)).
- **Port / encryption pairs** are not validated: an unusual pair (e.g. `ssl` on 587) is
  accepted and fails at send time with a hint (see
  [email/failures.md](../../../email/failures.md#port--encryption-hints)).
- An empty string (`""`) for `username` or `password` means "no value" (stored as `NULL`).
- Messages follow [conventions/validation.md](../../conventions/validation.md#message-templates).
  For the rules without a template there, the messages are: `port must be an integer`,
  `port must be between 1 and 65535`, `host must be a hostname or an IP address`,
  `<field> must not contain control characters`, `<field> must not contain line breaks`.

## `GET /smtp-connections.json`

Lists the organization's connections, ordered by `id` ascending, not paginated.

Success: **`200`**, `{ "smtpConnections": [<SMTP connection item>, …] }` (an empty array when
there is none).

## `POST /smtp-connections.json`

Creates a connection.

Request:

```json
{
  "name": "Office relay",
  "host": "smtp.example.com",
  "port": 587,
  "encryption": "tls",
  "username": "relay-user",
  "password": "s3cret",
  "fromAddress": "noreply@example.com"
}
```

- Field rules as above; `username` and `password` absent, `null` or `""` mean "no
  authentication".
- `active` is **not accepted** (dropped like any unknown field): a new connection always
  starts **inactive**, even when it is the organization's first. It becomes active only
  through [activate](#post-smtp-connectionsidactivatejson).
- **Ordering**, in the create's transaction: `INSERT` the row with `password = NULL`, read
  the new id (`lastInsertId`), encrypt the password with the associated data built from the
  organization id and that id, then `UPDATE smtp_connections SET password = ? WHERE id = ?`
  (see [email/password-encryption.md](../../../email/password-encryption.md#write-path)). Any
  failure rolls the whole create back; a row without its password is never visible. Without
  credentials the encrypt/update step is skipped.
- An encryption failure answers `500 INTERNAL_ERROR` (the real error is logged without any
  password data).

Success: **`201`**, `{ "smtpConnection": <SMTP connection item> }`. One operator log line
(`smtp-connection-create`, the new id).

## `GET /smtp-connections/:id.json`

Reads one connection.

Success: **`200`**, `{ "smtpConnection": <SMTP connection item> }`.

## `PATCH /smtp-connections/:id.json`

Updates one connection.

Request (any subset of the fields):

```json
{ "port": 465, "encryption": "ssl", "password": "n3w-s3cret" }
```

- A field **absent or `null`** is unchanged.
- `""` **clears** `username` / `password` (stored as `NULL`); clearing both removes
  authentication.
- Required fields (`name`, `host`, `port`, `encryption`, `fromAddress`) cannot be cleared:
  `""` (or a whitespace-only string) is `400 VALIDATION_FAILED`.
- `active` is dropped: activation only goes through
  [activate](#post-smtp-connectionsidactivatejson).
- The credential pairing rule is checked on the merged state, so sending only `password` on a
  connection that has a `username` is allowed (it rotates the password).
- Changes apply to the next send; changing the active connection keeps it active.
- A new `password` is encrypted with a fresh nonce and the current key; without one, the
  stored bytes are kept as is (no re-encryption), even when `username`, `host` or `port`
  change (see [email/password-encryption.md](../../../email/password-encryption.md#write-path)).
  An encryption failure rolls the update back and answers `500 INTERNAL_ERROR`.

Success: **`200`**, `{ "smtpConnection": <SMTP connection item> }`, reloaded after the update.

- A value identical to the stored one is not a change. A present, non-empty `password` is
  always a change (it is not compared with the stored one).
- When at least one field changed, one operator log line (`smtp-connection-update`, the id,
  the **names** of the changed fields, e.g. `host`, `password`). With no change (including an
  empty body), the answer is `200` with the unchanged item and no log line.

## `DELETE /smtp-connections/:id.json`

Deletes one connection (**hard delete**: the row is removed).

Success: **`204`**, no body. One operator log line (`smtp-connection-delete`, the id).

- Deleting the **active** connection is allowed. The organization then has no active
  connection, and `POST /v1/messages` answers `409 NO_SMTP_CONNECTION`
  ([sending.md](../sending.md)) until another one is activated.

## `POST /smtp-connections/:id/activate.json`

Makes the connection the organization's **only** active connection. No body (any body is
ignored).

Steps, in **one transaction**:

1. lock the organization row (`SELECT … FROM organizations WHERE id = ? FOR UPDATE`);
2. load the target connection of the organization; unknown → `404`;
3. set `active = false` on every other connection of the organization;
4. set `active = true` on the target;
5. commit.

Success: **`200`**, `{ "smtpConnection": <SMTP connection item> }` (with `active: true`). One
operator log line (`smtp-connection-activate`, the id).

- Activating the already active connection answers `200` again (idempotent).
- The organization lock serializes concurrent activations: after two concurrent calls on
  different connections, exactly one connection is active.
- There is no deactivate endpoint in v1 (see [Pending decisions](#pending-decisions)); deleting
  the active connection is the only way to have none again.

## `POST /smtp-connections/:id/test.json`

Sends a fixed test email through the connection, so its settings can be checked.

Request:

```json
{ "to": "bob@example.com" }
```

| Field | Rules |
| --- | --- |
| `to` | optional; valid email address (after trimming), at most 255 characters, no CR or LF. Absent, `null` → the caller's own `users.email` |

Content (fixed, no caller-supplied content):

- subject: `Pidgey test email`;
- text body: `This is a test email sent by Pidgey to check an SMTP connection.`;
- no HTML part; the `From:` header and envelope sender are the connection's `fromAddress`,
  as for any send ([email/message-scope.md](../../../email/message-scope.md)).

Behavior:

- **Synchronous**, through the same send flow as `POST /v1/messages`
  ([email/send-flow.md](../../../email/send-flow.md), steps 5–8: connect, encryption, `AUTH`
  when credentials exist, `MAIL FROM` / `RCPT TO` / `DATA`, `QUIT`, log), with the same
  timeouts and TLS rules.
- It uses the **given** connection, **active or not**, so a connection can be tested before
  it is activated. It never changes `active` or any other data.
- The send-flow log line identifies the acting user instead of a token.
- When credentials are stored with `encryption: none` (a row stored before the
  credentials-require-encryption rule), the answer is `409 SMTP_INSECURE_CREDENTIALS` —
  `The SMTP connection has credentials but no encryption; set encryption to tls or ssl`,
  before the password is decrypted; the SMTP server is **never contacted** (see
  [email/send-flow.md](../../../email/send-flow.md#send-time-checks)).
- The `host` is resolved once and checked against the
  [host policy](../../../email/smtp-connections.md#host-policy): when
  `SMTP_ALLOW_PRIVATE_HOSTS` is `false` and any resolved address (or the IP literal) is in a
  denied range, the answer is `409 SMTP_HOST_NOT_ALLOWED` —
  `The SMTP host resolves to an address that is not allowed`, before the password is
  decrypted; the SMTP server is **never contacted** (see
  [email/send-flow.md](../../../email/send-flow.md#host-check)). Otherwise the transport
  connects to the checked IP.
- When credentials are stored, the password is decrypted before connecting. If it cannot be
  decrypted (wrong or removed key, corrupt bytes, associated-data mismatch, malformed bytes,
  unknown key id), the answer is `409 SMTP_PASSWORD_UNREADABLE` —
  `The stored SMTP password could not be decrypted; set it again`, and the SMTP server is
  **never contacted** (see
  [email/password-encryption.md](../../../email/password-encryption.md#read-path)). Recovery:
  `PATCH` a new `password`, or clear both credentials.

Success: **`201`**, the same shape as the `POST /v1/messages` success body:

```json
{ "status": "sent", "smtp": { "code": 250, "message": "OK: queued as 1a2b3c" } }
```

Failure: the `SMTP_*` codes, statuses and `error.smtp` rules of
[email/failures.md](../../../email/failures.md). Surfacing them is allowed because the caller
is authenticated (see
[conventions/errors.md](../../conventions/errors.md#smtp-send-failures)).
`NO_SMTP_CONNECTION` **never** applies: the connection is the one in the path.

One operator log line per attempt that reached the send (`smtp-connection-test`, the id and
the outcome: `sent` or the `SMTP_*` code), never the password and never the recipient's
address beyond what the send flow already logs.

| Case | Answer |
| --- | --- |
| Invalid `to` | `400 VALIDATION_FAILED` |
| Unknown or foreign connection | `404 NOT_FOUND` — `SMTP connection not found` |
| Stored credentials with `encryption: none` | `409 SMTP_INSECURE_CREDENTIALS` — `The SMTP connection has credentials but no encryption; set encryption to tls or ssl` |
| Host resolves to a denied address (`SMTP_ALLOW_PRIVATE_HOSTS=false`) | `409 SMTP_HOST_NOT_ALLOWED` — `The SMTP host resolves to an address that is not allowed` |
| Stored password cannot be decrypted | `409 SMTP_PASSWORD_UNREADABLE` — `The stored SMTP password could not be decrypted; set it again` |
| Connect, TLS, auth, rejection, temporary failure, invalid reply (`SMTP_PROTOCOL_ERROR`), timeout | `502` / `503` / `504` with the `SMTP_*` code, its fixed message and, when available, `error.smtp` |
| Sent | `201 { "status": "sent", "smtp": … }` |

## Errors summary

| Case | Answer |
| --- | --- |
| Cross-site `POST` / `PATCH` / `DELETE` | `403 FORBIDDEN` — `Cross-site request rejected` |
| Not authenticated, or user not `approved` | gate's `401` |
| Malformed `:id` | `400 BAD_REQUEST` — `Validation failed (numeric string is expected)` |
| Validation failure | `400 VALIDATION_FAILED` |
| Unknown, out of range or foreign `:id` | `404 NOT_FOUND` — `SMTP connection not found` |
| Stored credentials with `encryption: none` (test email only) | `409 SMTP_INSECURE_CREDENTIALS`, see [email/failures.md](../../../email/failures.md) |
| Host resolves to a denied address (test email only) | `409 SMTP_HOST_NOT_ALLOWED`, see [email/failures.md](../../../email/failures.md) |
| Stored password cannot be decrypted (test email only) | `409 SMTP_PASSWORD_UNREADABLE`, see [email/failures.md](../../../email/failures.md) |
| SMTP failure (test email only) | `502` / `503` / `504`, see [email/failures.md](../../../email/failures.md) |

## Known limitations

- **Server-side request forgery:** a user chooses the SMTP `host` and `port`. Internal
  addresses (loopback, private, link-local including the metadata address, shared address
  space, unspecified, multicast, reserved) are refused at send time by default with
  `409 SMTP_HOST_NOT_ALLOWED`, and the transport connects to the checked IP, so DNS
  rebinding cannot bypass the check (see
  [email/smtp-connections.md](../../../email/smtp-connections.md#host-policy)). Ports are not
  restricted, so the test email's error categories (`SMTP_CONNECTION_FAILED`,
  `SMTP_TIMEOUT`, `SMTP_TLS_FAILED`…) remain a reachability oracle for **public** hosts and
  ports — and for every host when `SMTP_ALLOW_PRIVATE_HOSTS` is `true` (development, test,
  or a self-hosted setup with an internal relay). Accepted in v1.
- **Credential redirection:** a `PATCH` may change `host`, `port` or `encryption` while
  keeping the stored password, so a hijacked session can make the server send the stored
  password to another server by then calling the test email. Sending it in cleartext is no
  longer possible: credentials with `encryption: none` are refused on update and at send
  time. Accepted in v1: every user of the organization has equal access, and the
  password belongs to the organization. Requiring the password to be re-sent is pending (see
  [Pending decisions](#pending-decisions) and [api/pending.md](../../pending.md)).
- **Slow servers:** the test email is synchronous; a slow SMTP server holds the request up to
  the send-flow timeouts, at most `SMTP_TOTAL_TIMEOUT` (see
  [email/send-flow.md](../../../email/send-flow.md#timeouts)).
- **Rows stored before the current rules** are not migrated. A row with credentials and
  `encryption: none` answers `409 SMTP_INSECURE_CREDENTIALS` on the test email; a row with
  a non-LDH `host` fails naturally at connect time (`SMTP_CONNECTION_FAILED`). Validation
  runs on the merged state, so any `PATCH` of such a row fails with `400` until
  `encryption` / `host` is fixed; list, show, activate and delete keep working.
- **No rate limit** on the test email endpoint (tracked in [api/pending.md](../../pending.md)).
- Lists are not paginated.

## Edge cases

- `PATCH` clearing `username` (`""`) while a password stays stored, or the reverse → `400`
  (pairing on the merged state).
- `PATCH` with only `password` on a connection that has a `username` → allowed (password
  rotation); the log line lists `password`.
- `POST` with only `username` (or only `password`) → `400`.
- `POST` with `active: true` → dropped; the connection is created inactive.
- `port` as a string (`"587"`), `0`, `65536` or `587.5` → `400`.
- `host` with a space, a CR/LF or another control character → `400`; `fromAddress` with
  CR/LF → `400`.
- Unicode `host` (`smtp.exämple.de`), a label over 63 characters, a label with a leading or
  trailing hyphen, or a hostname over 253 characters → `400` —
  `host must be a hostname or an IP address`; the punycode form is accepted.
- `POST` with credentials and `encryption: none` → `400` —
  `username and password require encryption tls or ssl`; `PATCH` setting
  `encryption: none` on a connection with credentials, or adding credentials to a `none`
  connection → the same `400`.
- `ssl` on port 587, or `tls` on 465 → accepted (no pair validation).
- Two concurrent `activate` calls on different connections → serialized by the organization
  lock; exactly one ends active.
- Deleting the active connection → no active connection; `POST /v1/messages` answers `409`.
- Test email on an inactive connection → allowed; on another organization's → `404`.
- Test email with no body → sent to the caller's own email.
- Test email with `to` containing CR/LF → `400 VALIDATION_FAILED`.
- Test email on a connection whose `host` is `127.0.0.1`, `169.254.169.254`, `::1`,
  `::ffff:10.0.0.1`, or a hostname with one public and one private address, with
  `SMTP_ALLOW_PRIVATE_HOSTS=false` → `409 SMTP_HOST_NOT_ALLOWED`, server never contacted;
  the same with the setting `true` → the send is attempted.
- Test email on a host that cannot be resolved → `SMTP_CONNECTION_FAILED`.

## Testable rules

The implementation must cover at least:

- every endpoint's success code and body shape; `password` never present in any response;
  `hasPassword` reflecting the stored state;
- the gate's `401` for anonymous calls, before `:id` parsing and validation; no admin check;
- organization isolation: `404` on every `:id` route for another organization's connection;
- the field rules, including the credential pairing and the credentials-require-encryption
  rule on the merged state, the `host` LDH rule and the header-injection character rules;
- a created connection is always inactive; `active` in a body is ignored;
- activate atomicity: at most one active connection after concurrent activations; activate
  is idempotent;
- delete of the active connection leaves none, and sending then answers
  `409 NO_SMTP_CONNECTION`;
- test email through Mailpit (success, `201`) and failure codes (unreachable host, wrong
  credentials); on an inactive connection; never `NO_SMTP_CONNECTION`;
- create ordering: the stored password decrypts with the new row's associated data, and a
  failure during encryption leaves no row;
- update without `password` keeps the stored bytes; with one, they change;
- test email on a stored row with credentials and `encryption: none` →
  `409 SMTP_INSECURE_CREDENTIALS`, the SMTP server never contacted;
- test email on a host resolving to a denied address with `SMTP_ALLOW_PRIVATE_HOSTS=false` →
  `409 SMTP_HOST_NOT_ALLOWED`, the SMTP server never contacted; the host resolved once and
  the connection made to the checked IP;
- test email with an undecryptable stored password → `409 SMTP_PASSWORD_UNREADABLE`, the SMTP
  server never contacted, `hasPassword` still `true`;
- operator log lines for every state change and test, with field names only and no secrets.

## Pending decisions

- **Pending decision:** a `deactivate` endpoint (`POST /smtp-connections/:id/deactivate.json`).
- **Pending decision:** a database-level constraint guaranteeing at most one active
  connection per organization (v1 relies on the locked transaction of activate).
- **Pending decision:** require `password` to be re-sent (or cleared) in the same `PATCH`
  when `host`, `port` or `encryption` changes on a connection that has credentials, against
  credential redirection (see [Known limitations](#known-limitations) and
  [api/pending.md](../../pending.md)).
- **Pending decision:** a rate limit on the test email endpoint
  ([api/pending.md](../../pending.md)).
- **Pending decision:** adopting the messages above for numeric ranges, hostnames and control
  characters as shared templates in
  [conventions/validation.md](../../conventions/validation.md#message-templates).
