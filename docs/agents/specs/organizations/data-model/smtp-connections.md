# SMTP Connections Table

Purpose: define the `smtp_connections` table holding organization SMTP settings.

Field meanings and security rules are defined in
[email/smtp-connections.md](../../email/smtp-connections.md). Conventions are in
[conventions.md](conventions.md).

## `smtp_connections`

| Column | Type | Null | Default | Notes |
| --- | --- | --- | --- | --- |
| `id` | BIGINT | no | auto | PK |
| `organization_id` | BIGINT | no | | FK → `organizations.id`; indexed |
| `name` | VARCHAR(255) | no | | Label shown in the UI |
| `host` | VARCHAR(255) | no | | SMTP server hostname |
| `port` | INT | no | | SMTP port |
| `encryption` | ENUM(`none`,`tls`,`ssl`) | no | | Transport encryption |
| `username` | VARCHAR(255) | yes | null | SMTP auth username |
| `password` | VARBINARY(2048) | yes | null | Encrypted at rest, see [email/password-encryption.md](../../email/password-encryption.md); never returned by the API |
| `from_address` | VARCHAR(255) | no | | Envelope sender and `From:` header |
| `active` | BOOLEAN | no | `false` | At most one active connection per organization |
| `active_organization_id` | BIGINT | yes | generated | Generated **stored** column `IF(active, organization_id, NULL)`; never written by the application, never exposed by the API |
| `created_at` | DATETIME | no | now | |
| `updated_at` | DATETIME | no | now, on update | |

Indexes: `organization_id`; unique `active_organization_id`
(`uq_smtp_connections_active_organization_id`).

## At most one active connection

The unique index on `active_organization_id` enforces **at most one active connection per
organization** in the database: an active row carries its `organization_id` there, an
inactive row `NULL`, and a unique index allows any number of `NULL`s.

- The index is **defense in depth**. The [activate endpoint](../../api/endpoints/pidgey-admin/smtp-connections.md#post-smtp-connectionsidactivatejson)
  keeps its locked transaction and still deactivates every other connection of the
  organization **before** activating the target, so the index is never hit in normal
  operation; a violation is a bug and answers `500 INTERNAL_ERROR`.
- Deactivate, create (always inactive) and update (never writes `active`) cannot violate it.

## Migrations

- `0008_create_smtp_connections.sql` creates the table (with `password VARBINARY(1024)`).
- `0009_widen_smtp_connections_password.sql` runs
  `ALTER TABLE smtp_connections MODIFY password VARBINARY(2048) NULL`, so the largest
  encrypted password (1069 bytes, see
  [email/password-encryption.md](../../email/password-encryption.md#byte-layout)) fits with
  margin for future format changes. Merged migrations are never edited (see
  [persistence/schema-migrations.md](../../persistence/schema-migrations.md)). No SMTP rows
  exist in production yet, so the `ALTER` is immediate.
- `0010_add_smtp_connections_active_unique.sql` adds the generated stored column
  `active_organization_id BIGINT GENERATED ALWAYS AS (IF(active, organization_id, NULL)) STORED`
  and the unique index `uq_smtp_connections_active_organization_id` on it. No SMTP rows exist
  in production, so there is no backfill or duplicate-active concern.

## Pending decisions

None.
