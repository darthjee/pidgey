# Idempotency Keys Table

`POST /v1/messages` may carry an optional `Idempotency-Key` header so a caller can retry
after losing the response without sending the message twice. Each organization stores, for
24 hours, the answer of every send that may have delivered the message. The rules (format,
flow, codes, store and release) are defined in
[api/endpoints/sending.md](../../api/endpoints/sending.md#idempotency-key) and
[email/send-flow.md](../../email/send-flow.md#idempotency-key-step). Conventions are in
[conventions.md](conventions.md).

## `idempotency_keys`

| Column | Type | Null | Default | Notes |
| --- | --- | --- | --- | --- |
| `id` | BIGINT | no | auto | PK |
| `organization_id` | BIGINT | no | | FK → `organizations.id`, `ON DELETE RESTRICT` |
| `key_hash` | CHAR(64) | no | | Lowercase hex SHA-256 of the raw key |
| `request_hash` | CHAR(64) | no | | Lowercase hex SHA-256 of the normalized payload |
| `state` | ENUM('in_flight','completed') | no | | |
| `response_status` | SMALLINT | yes | null | Set when `completed` |
| `response_body` | MEDIUMTEXT | yes | null | The JSON body, set when `completed` |
| `lease_until` | DATETIME | no | | Reservation time + 5 minutes |
| `expires_at` | DATETIME | no | | Reservation time + 24 hours |
| `created_at` | DATETIME | no | now | |
| `updated_at` | DATETIME | no | now, on update | |

Keys:

- unique (`organization_id`, `key_hash`) (`uq_idempotency_keys_organization_id_key_hash`);
- index (`organization_id`, `expires_at`) (`idx_idempotency_keys_organization_id_expires_at`);
- foreign key `fk_idempotency_keys_organization_id`.

## Usage

- **TTL:** `86400` seconds (24 hours) from the reservation; **lease:** `300` seconds
  (5 minutes); **cleanup batch:** `100` rows. All are code constants, no environment
  variables. An expired row is treated as absent and overwritten when its key comes back.
- **Reservation:** one `INSERT` relying on the unique key; a duplicate-key error means the
  key is known and its row is read. No lock is held around the SMTP transaction;
  completion is one `UPDATE`.
- **Lazy cleanup:** no new process. Reserving a key also deletes at most 100 expired rows of
  the same organization (`DELETE … LIMIT 100`).
- A `completed` row is replayed verbatim; an `in_flight` row past `lease_until` is completed
  as a stored `504 SMTP_TIMEOUT`. A releasing outcome deletes the row.
- Scope is the organization, not the API token: rotating a token mid-retry still finds the
  row, and organizations never see each other's keys.

## What is never stored

- The raw key (only `key_hash`).
- The message content (only `request_hash`). The stored body is the `{ status, smtp }`
  answer or the error envelope, which already carry no secret.

## Migrations

- `0012_create_idempotency_keys.sql` creates the table (one `CREATE TABLE`, see
  [persistence/schema-migrations.md](../../persistence/schema-migrations.md#rules)).

## Pending decisions

None.
