# Data Model Conventions

Purpose: define the column, key and constraint conventions every Pidgey table follows.

## Keys

- Every table has a `BIGINT AUTO_INCREMENT` primary key `id`.
- Every reference column is named `<entity>_id` (e.g. `organization_id`, `user_id`), typed
  `BIGINT`, and backed by a **physical** `FOREIGN KEY` constraint.
- Foreign keys, indexes and unique constraints are declared **inside** `CREATE TABLE`, so each
  table fits a one-statement migration (see
  [architecture/database.md](../../architecture/database.md#one-statement-per-file)).
- Foreign keys use `ON DELETE RESTRICT`: no user or organization is deleted in v1 (see
  [entities.md](../entities.md)).
- Every foreign key column is indexed (MySQL requires it; a unique or composite index whose
  first column is the foreign key counts).

## Timestamps

- `DATETIME` columns hold **UTC**.
- `created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`.
- `updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP`.
- Nullable event timestamps (`revoked_at`, `used_at`, ...) are `NULL` until the event
  happens.

## Secrets

- Secret tokens (API tokens, refresh tokens, password reset tokens, poll tokens) are never
  stored in plain text: only their **SHA-256 hex digest**, as `CHAR(64)`, with a **unique**
  constraint. Lookups hash the presented token and search by digest.
- User passwords are stored as `password_digest`, produced by
  `password_hash(PASSWORD_DEFAULT)` and checked with `password_verify()`.
- SMTP passwords must be recoverable, so they are encrypted at rest instead (see
  [email/smtp-connections.md](../../email/smtp-connections.md)).

## Charset and collation

- Tables use `utf8mb4` with the default `utf8mb4_0900_ai_ci` collation.
- Text comparisons, and therefore unique constraints, are **case-insensitive** (and
  accent-insensitive). In particular `users.username` and `users.email` are unique regardless
  of case: `Admin` and `admin` cannot both exist.
- Lookups by `username` or `email` use the same collation, so they match regardless of case.

## Migrations table

- `schema_migrations` belongs to the migrations runner and is specified in
  [architecture/database.md](../../architecture/database.md).

## Pending decisions

None.
