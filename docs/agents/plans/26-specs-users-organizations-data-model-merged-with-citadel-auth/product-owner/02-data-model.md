# 02 — Data model

Split `docs/agents/specs/organizations/data-model.md` into a folder (it grows to nine tables).
`data-model.md` becomes a short index (title, `Purpose:`, table of files, note that the
authoritative schema is the SQL migrations — see `architecture/database.md`).

Detail files under `docs/agents/specs/organizations/data-model/`, each with title,
`Purpose:`, column table(s) (Column / Type / Null / Default / Notes) and indexes:

- `conventions.md` — `BIGINT AUTO_INCREMENT` primary key `id`; `*_id` columns `BIGINT` with
  physical `FOREIGN KEY` constraints declared inside `CREATE TABLE` (fits one-statement
  migrations); `DATETIME` in UTC; `created_at DEFAULT CURRENT_TIMESTAMP`,
  `updated_at DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP`; secret tokens stored
  only as SHA-256 hex (`CHAR(64)`, unique); `utf8mb4`; `schema_migrations` belongs to the
  migrations runner (`architecture/database.md`).
- `organizations-and-users.md` — `organizations` (id, name, timestamps); `users`:
  `organization_id` (FK, NOT NULL, indexed), `username` (unique), `email` (unique),
  `password_digest` (`password_hash(PASSWORD_DEFAULT)`, replaces `password_hash`),
  `is_admin` (BOOLEAN, default false, never set by an endpoint), `status` (VARCHAR(16),
  `pending`/`approved`/`denied`, **no default**: every insert sets it), timestamps.
- `refresh-tokens.md` — `refresh_tokens`: `token_hash` (unique), `user_id` (FK, indexed),
  `issued_at`, `expires_at`, `revoked_at`, `revoked_reason`
  (`rotated`/`logout`/`password_reset`/`replay_detected`/`admin_denied`, set together with
  `revoked_at`), `session_uuid` (indexed; the session id), `started_at`. One line of meaning
  per column; lifetimes and rotation rules: link to `api/authentication.md` (#27).
- `password-reset-tokens.md` — `password_reset_tokens`: `token_hash` (unique), `user_id`
  (FK, indexed), `created_at`, `expires_at` (configurable lifetime), `used_at` (a used token
  is dead).
- `authorization-requests.md` — `authorization_requests` (device authorization): every
  column of the Citadel excerpt (`uuid` unique, `username` as typed, nullable `user_id` FK,
  `status` ENUM `open`/`approved`/`denied`/`logged`/`expired`, `poll_token_hash` unique,
  `request_ip`, `request_user_agent`, nullable `approved_by_user_id` FK to `users`,
  `created_at`, `expires_at`, `resolved_at`, `logged_at`, `authorize_failed_attempts`,
  `authorize_locked_until`); indexes: unique `uuid`, unique `poll_token_hash`,
  `(user_id, status)`, `expires_at`, plus `(request_ip, created_at)` and
  `(username, created_at)` for the rate limits. Flow: link to the device-authorization
  endpoint specs (#29) / `api/authentication.md`.
- `account-edit-lockouts.md` — `account_edit_lockouts`: `user_id` (FK, **unique**, one row per
  user, upserted), `failed_attempts` (default 0), `locked_until`, timestamps.
- `api-tokens.md` — `api_tokens` as today, `BIGINT`/FK; `scopes` is a `JSON` column (resolves
  the scopes-storage pending item).
- `smtp-connections.md` — `smtp_connections` as today (link to `email/smtp-connections.md`).

Resolve the old pending items ("username is an email?" → separate unique columns; scopes →
JSON). Physical FKs: decide `ON DELETE` as `RESTRICT` (no deletion in v1).

## Files to Change
- `docs/agents/specs/organizations/data-model.md` — becomes the index.
- `docs/agents/specs/organizations/data-model/*.md` — new detail files listed above.
