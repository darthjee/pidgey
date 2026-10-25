# Auth index and session endpoints

**`endpoints/auth.md`** — index of `auth/session.md`, `auth/recovery.md`,
`auth/account.md`, plus the **shared rules** of every `/auth/*.json` endpoint:

- Guard order and public vs gated routes (link `authentication/gate.md`); all are public
  except `PATCH /auth/account.json`.
- Trimming (decision 7): surrounding whitespace trimmed on `username`, `email`,
  `organizationName` before validation; "not empty" after trimming; passwords never trimmed.
- Identifiers stored as entered; uniqueness and lookups rely on the `utf8mb4_0900_ai_ci`
  collation (case- and accent-insensitive; `bób` logs in as `bob`). `username`/`email` max
  255 characters.
- Password hashing (decision 6): `password_hash(PASSWORD_DEFAULT)` (bcrypt, cost 12 on PHP
  8.4), `password_verify()`; every **new** password max 72 bytes (UTF-8); login `password`
  and `currentPassword` only "not empty".
- Shared `user` payload: link `authentication/gate.md#shared-user-payload`.
- Pending decisions: username character set; email-verification flow; login by email out of
  scope (state it, not as pending).

**`endpoints/auth/session.md`** — full contracts (request, field rules with validation
messages, success status/body, side effects, error table) for:

- `POST /auth/register.json` (decision 1, 5): fields `username`, `email`, `password`
  (min 8, max 72 bytes), **`organizationName`** (required, not empty, max 255; not unique).
  Pre-check username/email (username first) → `409 USERNAME_TAKEN` / `EMAIL_TAKEN`; insert
  organization + user in **one transaction**, `status` from `USER_APPROVAL_REQUIRED`
  (`approved` only when exactly `false`), `is_admin = false`; a unique-index violation maps
  to the same `409` and rolls back the organization; start a session; `201` + shared payload
  + cookie, same for pending and approved. State the **known limitation**: register reveals
  that a username/email exists (denied users included) — the documented exception to
  enumeration safety.
- `POST /auth/login.json` (decisions 6, 7, 8): username only; dummy-digest verify for
  unknown users (hard-coded `DUMMY_DIGEST`, a `PASSWORD_DEFAULT` hash; a unit test asserts
  `password_needs_rehash(DUMMY_DIGEST)` is false); status checked only after the password
  (`401 UNAUTHORIZED Invalid username or password`, `403 USER_DENIED`, pending → `201`);
  **rehash** when `password_needs_rehash()`; new session. No application-level throttle in
  v1 (link `api/pending.md`).
- `POST /auth/refresh.json`: the Citadel check table, linking `authentication/sessions.md`
  for rotation, replay detection and the conditional revoke (do not duplicate them).
- `DELETE /auth/logoff.json`: JSON body on DELETE; works whatever the token state; revokes the
  session's active rows with `logout`; unknown token → silent `204`; clears the cookie.
- `POST /auth/status.json`: passive; the four-row caller table (pending answer with
  `status: "pending"`; denied revokes the presented token with `admin_denied`).

## Files to Change
- `docs/agents/specs/api/endpoints/auth.md` — new index + shared rules.
- `docs/agents/specs/api/endpoints/auth/session.md` — new.
