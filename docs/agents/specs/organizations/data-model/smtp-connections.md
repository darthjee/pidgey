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
| `created_at` | DATETIME | no | now | |
| `updated_at` | DATETIME | no | now, on update | |

Indexes: `organization_id`.

## Migrations

- `0008_create_smtp_connections.sql` creates the table (with `password VARBINARY(1024)`).
- `0009_widen_smtp_connections_password.sql` runs
  `ALTER TABLE smtp_connections MODIFY password VARBINARY(2048) NULL`, so the largest
  encrypted password (1069 bytes, see
  [email/password-encryption.md](../../email/password-encryption.md#byte-layout)) fits with
  margin for future format changes. Merged migrations are never edited (see
  [persistence/schema-migrations.md](../../persistence/schema-migrations.md)). No SMTP rows
  exist in production yet, so the `ALTER` is immediate.

## Pending decisions

None.
