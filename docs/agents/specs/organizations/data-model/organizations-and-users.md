# Organizations and Users Tables

Purpose: define the `organizations` and `users` tables.

Conventions (keys, foreign keys, timestamps) are in [conventions.md](conventions.md); the
domain rules are in [entities.md](../entities.md).

## `organizations`

| Column | Type | Null | Default | Notes |
| --- | --- | --- | --- | --- |
| `id` | BIGINT | no | auto | PK |
| `name` | VARCHAR(255) | no | | Organization name, given at registration |
| `created_at` | DATETIME | no | now | |
| `updated_at` | DATETIME | no | now, on update | |

## `users`

| Column | Type | Null | Default | Notes |
| --- | --- | --- | --- | --- |
| `id` | BIGINT | no | auto | PK |
| `organization_id` | BIGINT | no | | FK → `organizations.id`; indexed. Required for every user, admins included; never changes after insert |
| `username` | VARCHAR(255) | no | | **Unique** (case-insensitive). Used to log in |
| `email` | VARCHAR(255) | no | | **Unique** (case-insensitive). Used for account recovery |
| `password_digest` | VARCHAR(255) | no | | `password_hash(PASSWORD_DEFAULT)`; checked with `password_verify()` |
| `is_admin` | BOOLEAN | no | `false` | Global instance admin (user management only). No endpoint sets it |
| `status` | ENUM(`pending`,`approved`,`denied`) | no | **none** | Deliberately no default: every insert sets it (see [entities.md](../entities.md#user-status-lifecycle)) |
| `created_at` | DATETIME | no | now | |
| `updated_at` | DATETIME | no | now, on update | |

Indexes: unique `username`, unique `email`, `organization_id`. Uniqueness is
case-insensitive (see [conventions.md](conventions.md#charset-and-collation)).

`username` and `email` are separate columns: the username is not required to be an email
address.

## Pending decisions

None.
