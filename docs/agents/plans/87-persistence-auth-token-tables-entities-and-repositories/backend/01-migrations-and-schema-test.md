# Migrations and schema test

Create one `CREATE TABLE` file per table, matching the table specs column for column.

Rules for every file:

- `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci`;
- no `IF NOT EXISTS`;
- every FK is `ON DELETE RESTRICT`;
- explicit index and constraint names.

## Tables

- **`0003_create_refresh_tokens.sql`**
  - Columns: `id`, `token_hash CHAR(64)`, `user_id BIGINT`,
    `issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`, `expires_at DATETIME NOT NULL`,
    `revoked_at DATETIME NULL`, `revoked_reason VARCHAR(32) NULL`,
    `session_uuid VARCHAR(36) NOT NULL`, `started_at DATETIME NOT NULL`.
  - Keys: `UNIQUE uq_refresh_tokens_token_hash`, `KEY ix_refresh_tokens_user_id`,
    `KEY ix_refresh_tokens_session_uuid`, `fk_refresh_tokens_user_id → users(id)`.
- **`0004_create_password_reset_tokens.sql`**
  - Columns: `id`, `token_hash CHAR(64)`, `user_id`, `created_at` (default now),
    `expires_at`, `used_at NULL`.
  - Keys: `uq_password_reset_tokens_token_hash`, `ix_password_reset_tokens_user_id`,
    `fk_password_reset_tokens_user_id`.
- **`0005_create_authorization_requests.sql`**
  - Columns: `id`, `uuid VARCHAR(36)`, `username VARCHAR(255)`, `user_id BIGINT NULL`,
    `status ENUM('open','approved','denied','logged','expired') NOT NULL DEFAULT 'open'`,
    `poll_token_hash CHAR(64)`, `request_ip VARCHAR(45)`, `request_user_agent VARCHAR(512)`,
    `approved_by_user_id BIGINT NULL`, `created_at` (default now), `expires_at`,
    `resolved_at NULL`, `logged_at NULL`, `authorize_failed_attempts INT NOT NULL DEFAULT 0`,
    `authorize_locked_until DATETIME NULL`.
  - Unique keys: `uq_authorization_requests_uuid`,
    `uq_authorization_requests_poll_token_hash`.
  - Plain indexes:
    - `ix_authorization_requests_user_id_status (user_id, status)`, which also serves the
      `user_id` FK;
    - `ix_authorization_requests_expires_at`;
    - `ix_authorization_requests_request_ip_created_at (request_ip, created_at)`;
    - `ix_authorization_requests_username_created_at (username, created_at)`;
    - `ix_authorization_requests_approved_by_user_id`.
  - FKs: `fk_authorization_requests_user_id`,
    `fk_authorization_requests_approved_by_user_id`, both → `users(id)`.
- **`0006_create_account_edit_lockouts.sql`**
  - Columns: `id`, `user_id`, `failed_attempts INT NOT NULL DEFAULT 0`,
    `locked_until DATETIME NULL`, `created_at`, `updated_at` (on update).
  - Keys: `uq_account_edit_lockouts_user_id`, which also serves the FK, and
    `fk_account_edit_lockouts_user_id`.

## SchemaTest

Changes to `SchemaTest`:

- Add the four tables to `TABLES`.
- Per table, add `…Columns`, `…UniqueKeys`, `…Indexes`, `…ForeignKeys` and `…Collation`
  tests through the existing helpers.
  - Expect no extra auto-generated index: every FK must be covered by a named index.
- Add shared constants as needed: `CHAR_64`, a nullable `DATETIME`, and a `DATETIME` without
  a default.
- `issued_at`/`created_at` use the existing `CREATED_AT` shape.

Then run `composer db:status` locally: nothing should be pending or edited.

## Files to Change

- `source/migrations/0003_create_refresh_tokens.sql` — new.
- `source/migrations/0004_create_password_reset_tokens.sql` — new.
- `source/migrations/0005_create_authorization_requests.sql` — new.
- `source/migrations/0006_create_account_edit_lockouts.sql` — new.
- `source/tests/integration/Schema/SchemaTest.php` — tables and expectations.
