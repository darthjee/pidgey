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
| `password` | VARBINARY(1024) | yes | null | Encrypted at rest; never returned by the API |
| `from_address` | VARCHAR(255) | no | | Envelope sender and `From:` header |
| `active` | BOOLEAN | no | `false` | At most one active connection per organization |
| `created_at` | DATETIME | no | now | |
| `updated_at` | DATETIME | no | now, on update | |

Indexes: `organization_id`.

## Pending decisions

None.
