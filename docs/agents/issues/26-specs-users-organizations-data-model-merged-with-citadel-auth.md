# Issue: Specs: users & organizations data model (merged with Citadel auth)

## Description

Part of the epic #24 (Backend endpoint specs: enhance `docs/agents/specs/api` with the Citadel
contract). The React admin frontend was copied from Citadel and already calls Citadel's API
contract, so Pidgey's specs follow it. This sub-issue aligns the **organization specs** with the
merged users/organizations model and the Citadel auth tables. It is **specs only**: no
migrations, PHP or frontend changes. It can start right away (in parallel with #25, already
merged); #27 (authentication model) builds on it.

Epic decisions that apply here:

- **Enhance the existing specs** instead of creating a new tree. When a file grows too big,
  split it into a folder following the granularity rule in `docs/agents/specs.md`
  (`<name>.md` becomes a short index of `<name>/*.md`). Every spec file keeps the usual format:
  title, `Purpose:` line, content, **Pending decisions**.
- The Citadel contract **replaces** the conflicting parts of the existing specs (JWT
  `access_token` cookie + refresh token instead of the PHP session, `camelCase` fields, etc.).
- **Users and organizations, merged model:** every user belongs to exactly one organization;
  `is_admin` is a global instance admin; equal access within an organization; registration
  creates the organization together with the first user; user `status`
  (`pending`/`approved`/`denied`) and `USER_APPROVAL_REQUIRED` apply.
- Adapt Citadel-specific details to Pidgey (drop the `CITADEL_PLACEHOLDER_` prefix, Citadel
  source links, the "Tent" proxy) and record anything not settled as a **Pending decision**.

## Problem

The organization specs still describe the pre-Citadel model:

- `organizations/entities.md` has no admin, no user status, and an open question on how users
  join an organization.
- `organizations/data-model.md` has `users` with only `username`/`password_hash`, and none of
  the tables the Citadel auth features need (refresh tokens, password reset tokens, device
  authorization requests, account-edit lockouts).
- `organizations/bootstrap.md` registers with username/password/organization name only and
  logs in with a classic PHP session.
- `overview/terminology.md` lacks Admin, User status, Session, Access/Refresh token and
  Authorization request.

## Expected Behavior

### Decisions

- **Table naming:** plain names, consistent with the existing tables: `users`,
  `refresh_tokens`, `password_reset_tokens`, `authorization_requests`,
  `account_edit_lockouts` (no Citadel `auth_*` prefix).
- **Keys:** keep the Pidgey style: `BIGINT` primary keys and **physical** `FOREIGN KEY`
  constraints (declared inside `CREATE TABLE`, compatible with one-statement migrations), not
  Citadel's `INT` with logical-only foreign keys. Other Citadel column conventions apply:
  `DATETIME` in UTC, `created_at DEFAULT CURRENT_TIMESTAMP`,
  `updated_at ... ON UPDATE CURRENT_TIMESTAMP`, SHA-256 hex token hashes, the listed indexes.
- **Joining an organization:** registration always creates a **new** organization with its
  first user. How additional users join an existing organization stays a **Pending decision**
  (no endpoint in this epic), so for now an organization effectively has one user.
  Registration is open; `USER_APPROVAL_REQUIRED` (pending + admin approval) is the control.
  This resolves the "registration stays open" pending item in `bootstrap.md`.
- **Admin:** a normal user of one organization (`organization_id` is `NOT NULL` for every
  user) with `is_admin = true`. The extra power is **only user management** across all users
  (the `/admin/users/*` endpoints: search, edit, approve, deny, recovery link/email). The admin
  has no access to other organizations' SMTP connections or API tokens; organization isolation
  applies to them like to anyone else.
- **Denied / pending users and their organization:** the organization is always created at
  registration and is never removed. A pending user cannot reach any protected route (gate,
  see #27), so their organization stays unused. After a deny, the organization's API tokens
  keep working; whether a deny should revoke them is a **Pending decision**.
- **First admin:** no endpoint creates or promotes admins (`is_admin` is set out of band). How
  the first admin is created in production (and whether a development seed exists) is a
  **Pending decision**, to be handled by a future issue. No `demo` seed user.
- **Credentials:** `username` and `email` are separate columns, each **unique** (login is by
  username; email is used for recovery). The column is named `password_digest`, hashed with
  `password_hash(PASSWORD_DEFAULT)` and checked with `password_verify()`. This resolves the
  "username is an email?" pending item.
- **No deletion** of users or organizations in v1 (deny replaces user removal).
- **`api_tokens.scopes`:** a `JSON` column. This resolves the scopes-storage pending item.

### Spec changes

| File | Change |
| --- | --- |
| `organizations/entities.md` | Merged model: user belongs to exactly one organization; `is_admin` global admin limited to user management; equal access within an organization; user `status` lifecycle (`pending`/`approved`/`denied`, initial value from `USER_APPROVAL_REQUIRED`; `denied` users look unknown except a correct-password login). Pending: joining an existing organization. Resolved: deletion (none in v1). |
| `organizations/data-model.md` | `users` gains `email` (unique), `password_digest` (replaces `password_hash`), `is_admin` (default false), `status` (no default; every insert sets it). Add `refresh_tokens`, `password_reset_tokens`, `authorization_requests`, `account_edit_lockouts` with the columns and indexes of the Citadel tables below (adapted to plain names, `BIGINT`, physical FKs). Keep `organizations`, `api_tokens` (scopes `JSON`), `smtp_connections`, `schema_migrations`. Each auth column gets a one-line meaning and a link to `api/authentication.md` for the mechanics (#27). If the file grows too big, split it into `data-model/` (e.g. organizations, users, auth tables, API tokens, SMTP connections) with `data-model.md` as the index. |
| `organizations/bootstrap.md` | Registration (username, email, password, organization name) creates organization + user in one transaction, sets the initial `status`, and starts a session (tokens; mechanics in #27). First admin: Pending decision. The **Login** section (PHP session) is removed and replaced by a pointer to `api/authentication.md`. |
| `organizations/scope.md` | "session user" becomes "authenticated user"; the admin's user-management endpoints are the only cross-organization access, and only over users. |
| `overview/terminology.md` | Add Admin, User status, Session, Access token, Refresh token, Authorization request; update User (username + email, status) and Organization (created at registration). |

### Out of scope

- **#27 (authentication model):** token mechanics (JWT `access_token` cookie, refresh rotation,
  replay detection), what the gate does with `status`, and the rewrite of
  `api/authentication.md`. Until #27 lands, the pointer in `bootstrap.md` leads to a spec still
  describing the PHP session; accepted, both being specs-only parts of the same epic.
- **#28–#31 (endpoints):** request/response payloads (e.g. the `register.json` body in
  `camelCase`) and error codes such as `USER_DENIED`.
- **#32 (frontend compatibility):** the copied register form has no organization-name field
  (no "organization" appears anywhere under `frontend/assets`); a note for #32, no frontend
  change here.
- The first-admin mechanism (future issue), migrations and PHP code.

## Solution

Work by the product-owner agent, specs only:

1. Rewrite `organizations/entities.md` with the merged model, the admin's limited power and the
   status lifecycle.
2. Update `organizations/data-model.md` (splitting into `data-model/` if needed) with the new
   `users` columns and the four auth tables, using plain names, `BIGINT` keys and physical FKs;
   resolve the username/email and scopes pending items.
3. Rewrite `organizations/bootstrap.md`: registration flow, initial status, first-admin pending
   decision, Login section replaced by a pointer to `api/authentication.md`.
4. Adjust `organizations/scope.md` and `overview/terminology.md`.
5. Check links: `architecture/database.md` (points to the data model), `api/authentication.md`
   and `api/endpoints.md` references stay valid.

The security agent should review the data model (token hashes, lockout counters, the admin's
reach) before merging.

## Benefits

- The data model covers every Citadel feature the frontend already uses, so #27–#31 can specify
  endpoints against concrete tables.
- Long-standing pending items (registration openness, username vs. email, scopes storage,
  deletion) are resolved; the remaining ones (joining an organization, first admin, deny vs.
  API tokens) are explicit.
- The admin's power is bounded to user management, keeping organization isolation intact.

### Reference: Citadel contract (verbatim excerpts)

Excerpts from "Citadel API — endpoint contract for a reimplementation". Links point to the Citadel source tree and are kept for traceability only.

#### 3.5 User status

`auth_users.status` is one of `pending`, `approved`, `denied`.

- Only `approved` users pass the gate.
- New registrations start `pending` when `USER_APPROVAL_REQUIRED=true`, otherwise `approved`.
- `pending` users can log in and get a real session (tokens issued), but the gate blocks
  every protected route until an admin approves them. `status.json` tells the frontend they are
  pending.
- `denied` users look like unknown users everywhere, except a login with the **correct**
  password, which answers `403 USER_DENIED`.

#### 8. Database models

MySQL 8. Every table uses an `INT AUTO_INCREMENT` primary key `id`. Foreign keys are
**logical only**: there are no physical FK constraints, just indexed `user_id` columns.
`DATETIME` columns hold UTC. `created_at` defaults to `CURRENT_TIMESTAMP`, and `updated_at`
defaults to `CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP`.

Entities: [`auth/entities/`](/Users/darthjee/projetos/orca/citadel/backend/src/auth/entities/).
Migrations: [`database/migrations/`](/Users/darthjee/projetos/orca/citadel/backend/src/database/migrations/).

##### 8.1 `auth_users`

| Column | Type | Null | Default | Notes |
| --- | --- | --- | --- | --- |
| `id` | INT | no | auto | PK |
| `username` | VARCHAR(255) | no | | **unique** |
| `email` | VARCHAR(255) | no | | **unique** |
| `password_digest` | VARCHAR(255) | no | | bcrypt hash, cost 10 |
| `is_admin` | BOOLEAN | no | `false` | No endpoint changes it; set it directly in the DB |
| `status` | VARCHAR(16) | no | **none** | `pending` / `approved` / `denied`. There is deliberately no default: every insert must set it |
| `created_at` | DATETIME | no | now | |
| `updated_at` | DATETIME | no | now, on update | |

##### 8.2 `auth_refresh_tokens`

| Column | Type | Null | Default | Notes |
| --- | --- | --- | --- | --- |
| `id` | INT | no | auto | PK |
| `token_hash` | VARCHAR(255) | no | | SHA-256 hex of the token, **unique** |
| `user_id` | INT | no | | → `auth_users.id`; index `idx_auth_refresh_tokens_user_id` |
| `issued_at` | DATETIME | no | now | |
| `expires_at` | DATETIME | no | | issued + 7 days |
| `revoked_at` | DATETIME | yes | null | |
| `revoked_reason` | VARCHAR(32) | yes | null | `rotated` / `logout` / `password_reset` / `replay_detected` / `admin_denied`; set together with `revoked_at` |
| `session_uuid` | VARCHAR(36) | no | | Session id (= JWT `sid`); index `idx_auth_refresh_tokens_session_uuid` |
| `started_at` | DATETIME | no | | When the session's login happened; copied on rotation |

##### 8.3 `auth_password_reset_tokens`

| Column | Type | Null | Default | Notes |
| --- | --- | --- | --- | --- |
| `id` | INT | no | auto | PK |
| `token_hash` | VARCHAR(255) | no | | SHA-256 hex, **unique** |
| `user_id` | INT | no | | index `idx_auth_password_reset_tokens_user_id` |
| `created_at` | DATETIME | no | now | |
| `expires_at` | DATETIME | no | | created + 30 min (configurable) |
| `used_at` | DATETIME | yes | null | Set on successful reset; a used token is dead |

##### 8.4 `auth_authorization_requests`

| Column | Type | Null | Default | Notes |
| --- | --- | --- | --- | --- |
| `id` | INT | no | auto | PK |
| `uuid` | VARCHAR(36) | no | | Public id, **unique** |
| `username` | VARCHAR(255) | no | | As typed by the requester |
| `user_id` | INT | yes | null | Resolved user; null when the username doesn't exist |
| `status` | ENUM(`open`,`approved`,`denied`,`logged`,`expired`) | no | `open` | |
| `poll_token_hash` | VARCHAR(255) | no | | SHA-256 hex, **unique** |
| `request_ip` | VARCHAR(45) | no | | |
| `request_user_agent` | VARCHAR(512) | no | | |
| `approved_by_user_id` | INT | yes | null | |
| `created_at` | DATETIME | no | now | |
| `expires_at` | DATETIME | no | | created + 1 h (configurable) |
| `resolved_at` | DATETIME | yes | null | Set on approve / deny / expire |
| `logged_at` | DATETIME | yes | null | Set on the winning claim |
| `authorize_failed_attempts` | INT | no | `0` | Per-row authorize lockout counter |
| `authorize_locked_until` | DATETIME | yes | null | |

Indexes: unique `uuid`, unique `poll_token_hash`, `(user_id, status)`, `expires_at`. The rate
limit also counts by `request_ip` + `created_at` and by `username` + `created_at`, so consider
indexes on those as well.

##### 8.5 `auth_account_edit_lockouts`

| Column | Type | Null | Default | Notes |
| --- | --- | --- | --- | --- |
| `id` | INT | no | auto | PK |
| `user_id` | INT | no | | **unique** (one row per user, upserted) |
| `failed_attempts` | INT | no | `0` | |
| `locked_until` | DATETIME | yes | null | |
| `created_at` | DATETIME | no | now | |
| `updated_at` | DATETIME | no | now, on update | |

##### 8.6 Seed data (non-production only)

The migrations seed a `demo` user (`demo@citadel-placeholder.test`), whose password comes from
an env var, and then promote it to admin. Both steps are skipped when `STAGE=production`. No
endpoint creates an admin, so a production deployment needs some out-of-band way to set
`is_admin = true` on the first account.

