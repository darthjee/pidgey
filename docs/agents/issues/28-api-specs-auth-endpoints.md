# Issue: API specs: auth endpoints

## Description

Part of the epic #24 (Backend endpoint specs: enhance `docs/agents/specs/api` with the Citadel
contract). The React admin frontend was copied from Citadel and already calls Citadel's API
contract, so Pidgey's specs follow that contract. This sub-issue is **specs only**: no PHP
code, migrations or frontend changes.

Decisions taken in the epic that apply here:

- **Enhance the existing specs** instead of creating a new tree. When a file grows too big,
  split it into a folder following the granularity rule in `docs/agents/specs.md`
  (`<name>.md` becomes a short index of `<name>/*.md`). Every spec file keeps the usual format:
  title, `Purpose:` line, content, **Pending decisions**.
- The Citadel contract **replaces** the conflicting parts of the existing specs: root-relative
  `*.json` paths with no `/v1` prefix for admin/auth endpoints, JWT `access_token` cookie +
  refresh token instead of the PHP session, `camelCase` fields, the Citadel error body, and
  `400 VALIDATION_FAILED` for validation errors.
- **Pidgey-only admin endpoints** (organization, SMTP connections, API tokens, test email)
  follow the Citadel conventions. The **public sending API** (`POST /v1/messages`, Bearer
  tokens with scopes) keeps its own `/v1` contract.
- **Users and organizations, merged model:** every user belongs to exactly one organization
  (`organization_id`); `is_admin` is a global instance admin who manages users and approvals;
  within an organization access stays equal; registration creates the organization together
  with the first user; user `status` (`pending`/`approved`/`denied`) and
  `USER_APPROVAL_REQUIRED` apply.
- All Citadel features are in scope: auth, user approval/admin, device authorization,
  password recovery (emails sent by Pidgey through SMTP), account edit with lockout,
  operational endpoints.

Adapt Citadel-specific details to Pidgey when writing the specs (e.g. drop the
`CITADEL_PLACEHOLDER_` config prefix, the Citadel source links, and the "Tent" proxy name
where it does not apply), and record anything not settled as a **Pending decision**.

## Problem

`docs/agents/specs/api/endpoints.md` still describes the old PHP-session endpoints
(`POST /v1/registration`, `/v1/session`) while the admin frontend, copied from Citadel, already
calls `/auth/*.json`. The authentication model (#27) defines tokens, sessions and the gate,
but no spec yet gives the request/response contracts of the auth endpoints, the password
recovery flow (including who sends the recovery email from a PHP app with no queue) or the
account edit lockout. Citadel's contract also contains Node-specific details (fire-and-forget
email, bcrypt cost 10, millisecond settings) that do not fit Pidgey as-is.

## Expected Behavior

Turn `docs/agents/specs/api/endpoints.md` into an index of `docs/agents/specs/api/endpoints/`
and write `api/endpoints/auth.md` (split further if it grows too big) with the full contracts:

- `POST /auth/register.json` (Pidgey: also creates the organization; decide the organization
  name field), `POST /auth/login.json`, `POST /auth/refresh.json`, `DELETE /auth/logoff.json`,
  `POST /auth/status.json`.
- `POST /auth/recover.json`, `POST /auth/reset-password.json` and password-reset tokens,
  including the recovery email (sent by Pidgey through SMTP; decide which SMTP connection /
  config sends system emails, or mark pending).
- `PATCH /auth/account.json` with the failed-attempt lockout.
- For each: request, field rules, success status and body, side effects, error cases.

These decisions override the Citadel excerpts below wherever they differ.

### 1. Organization name at registration

- `POST /auth/register.json` takes a **required** `organizationName` field (camelCase).
- Rules: string, not empty (after trimming), max length 255
  (`organizationName must be shorter than or equal to 255 characters`). Validated together
  with the other fields; all errors reported at once.
- Organization names are **not unique**: no new `409`, no enumeration vector.
- The organization and the user are inserted in **one transaction**; if the user insert fails
  (e.g. a `USERNAME_TAKEN` race on the unique index) the organization row is rolled back.
- The response stays the shared `user` payload (no organization in it); the organization is
  read through `GET /organization.json` (#31).
- Follow-up for #32: the register form and `AccountsClient.register` must send
  `organizationName` (the copied frontend sends only `username`, `email`, `password`,
  `password_confirmation`).

### 2. System emails are sent through the system organization

System emails (password recovery here, admin send-recovery-email in #30) are sent through the
**active SMTP connection of the system organization**: one organization marked as the
instance's own ("self"). No `SYSTEM_SMTP_*` environment settings.

- **How** the system organization is marked and who marks it is **deferred to the setup flow**
  (the same future issue as the first-admin Pending decision in
  `organizations/bootstrap.md`). #28 records it as a **Pending decision** (e.g. an
  `is_system` flag on `organizations`, at most one, set by the setup flow) and adds no column.
- The email goes through the **same SMTP client** as the send flow (timeouts, TLS policy,
  failure categories), using the connection's `from_address`.
- **Not sent** — a logged no-op that reports "not sent" — when there is no system
  organization, it has no active connection, or `FRONTEND_BASE_URL` is empty (the link would
  be broken). Recover still answers `200 { "sent": true }`; the "not sent" result only matters
  to #30's admin send-recovery-email.
- Content: subject `Reset your Pidgey password`, plain text, link
  `{FRONTEND_BASE_URL}/#/recover-password?token={token}`, plus the single-use / expiry /
  ignore-if-not-requested note.
- **Security consideration** (recorded in the spec): every member of the system organization
  can change its SMTP connection, and therefore redirect or read password-reset emails of the
  whole instance; the system organization is trusted like the admin. To be revisited with the
  setup flow.
- Rejected: dedicated `SYSTEM_SMTP_*` env settings; the user's own organization's connection
  (a fresh organization has none).

### 3. Recovery email is sent synchronously

Citadel (Node) sends the recovery email fire-and-forget. Pidgey runs PHP under Apache
mod_php, where every request is synchronous and nothing runs after the response (and
`send-flow.md` already defines sending as synchronous, with no queue). So:

- `POST /auth/recover.json` sends the email **inline**, before answering. Every failure
  (no sender configured, connection, TLS, auth, rejection, timeout) is **logged and
  swallowed**: the answer is always `200 { "sent": true }`, never an `SMTP_*` code or
  `error.smtp` (as `conventions/errors.md` already requires for endpoints reachable without
  login).
- The spec drops "asynchronously / fire-and-forget" and states the **known limitation**: the
  response time reveals whether the email belongs to an `approved`/`pending` user (SMTP round
  trip vs. instant answer). The SMTP connect/command timeouts bound that response time.
- Rejected alternatives: flushing the response then sending (`Connection: close` +
  `ignore_user_abort`) — fragile under mod_php (output buffering, compression, proxies; no
  `fastcgi_finish_request()`), hard to test; an outbox table + worker now — too large for a
  specs-only sub-issue.
- New **Pending decision**: an outbox table + CLI worker (or cron) to send system emails
  asynchronously, closing the timing leak; it also applies to the admin send-recovery-email
  (#30).

### 4. Configuration

`architecture/configuration.md` gains a section **"Password recovery and account edit"**
(bare names, seconds, positive integers):

| Setting | Default | Effect |
| --- | --- | --- |
| `PASSWORD_RESET_TOKEN_TTL` | `1800` (30 min) | Password-reset token lifetime: `expires_at = created_at + TTL`. |
| `ACCOUNT_EDIT_MAX_ATTEMPTS` | `5` | Failed `PATCH /auth/account.json` attempts before locking. |
| `ACCOUNT_EDIT_LOCK_TTL` | `300` (5 min) | Lock duration: `locked_until = now + TTL`. |

Other edits:

- Rules: counts (e.g. `ACCOUNT_EDIT_MAX_ATTEMPTS`) must be **positive integers**, otherwise
  boot is refused (today only durations are covered).
- `FRONTEND_BASE_URL` row: empty → system emails are not sent (decision 2).
- The trailing sentence becomes "Settings for device authorization (#29) are added by that
  spec" (account-edit lockout belongs to #28).
- `organizations/data-model/account-edit-lockouts.md` and `password-reset-tokens.md` point to
  `configuration.md` and the new endpoint spec instead of "`api/authentication.md` (#27)".
- No mail-enabled flag and no `SYSTEM_SMTP_*` settings (decision 2).

### 5. Inherited Pending decisions

**5a. `USER_APPROVAL_REQUIRED` defaults to `true`.**

- Rationale: open registration gives a stranger an organization that can store SMTP
  connections and make Pidgey's server open TCP connections to arbitrary hosts/ports (test
  email, `/v1/messages`) — an SSRF-style probe of the internal network. Openness is opt-in.
- This setting is the documented **exception to the boolean rule**: approval is disabled only
  by the exact value `false`; anything else (unset, typo) keeps approval required, so a typo
  never silently opens registration.
- #28 updates the `architecture/configuration.md` row (default `true`, exception noted in the
  Rules), the wording in `api/authentication/gate.md` and `organizations/entities.md`
  (registrations start `approved` only when the value is exactly `false`), resolves the
  Pending decision in `organizations/bootstrap.md`, and records the SSRF note as a security
  consideration. The development `.env` may set `USER_APPROVAL_REQUIRED=false`.

**5b. Register's `USERNAME_TAKEN` / `EMAIL_TAKEN` are a known limitation.**

- Uniqueness inherently reveals existence; hiding only denied users is not possible without
  hiding everyone. Register is the documented exception to enumeration safety, for denied
  users and in general. The Pending decision in `organizations/bootstrap.md` is resolved this
  way.
- Rejected: a generic `409` (still reveals that something is taken, worse UX). New **Pending
  decision**: an email-verification flow, which would allow real enumeration safety.

### 6. Password hashing

- New passwords (register, reset-password, account `newPassword`) are hashed with
  `password_hash(PASSWORD_DEFAULT)` (bcrypt, cost 12 on PHP 8.4) and checked with
  `password_verify()`, as `organizations/data-model/organizations-and-users.md` already says.
  The specs never say "bcrypt cost 10"; there is no cost setting.
- **Rehash on login:** after a successful `password_verify()`, if `password_needs_rehash()`
  is true, store a fresh digest. Account edit does not rehash.
- **Dummy digest:** login with an unknown username still runs
  `password_verify(password, DUMMY_DIGEST)`. `DUMMY_DIGEST` is a hard-coded
  `PASSWORD_DEFAULT` hash of a random throwaway string (not computed per request). A unit test
  asserts `password_needs_rehash(DUMMY_DIGEST)` is false, so a change of PHP's default fails
  the test instead of silently diverging the timing. The user status is checked only after
  the password.
- **72-byte limit:** every new password has a max length of **72 bytes** (UTF-8 byte length),
  so bcrypt never silently truncates. New validation template in
  `api/conventions/validation.md`: `<field> must be shorter than or equal to N bytes`.
  Login `password` and account `currentPassword` keep only "not empty": an over-long value
  simply fails verification (`401` / `400 Invalid current password`).

### 7. Login identifier and normalization

- **Login by `username` only** (as Citadel and the frontend). Email is used only for
  recovery. Login by email is out of scope.
- **Trimming:** surrounding whitespace is trimmed on `username`, `email` and
  `organizationName` **before validation** (register, login, recover, account edit); "not
  empty" is checked after trimming. Passwords are **never** trimmed.
- Values are **stored as entered** (after trimming); case is kept for display. Uniqueness and
  lookups rely on the `utf8mb4_0900_ai_ci` collation (case- and accent-insensitive), with no
  lowercasing in PHP. The spec states the consequence: `bób` logs in as `bob`, and register
  answers `USERNAME_TAKEN` for it.
- `username` and `email`: max 255 characters (the `VARCHAR(255)` columns). No other
  character restriction on `username`; new **Pending decision**: a username character set
  (control characters, inner whitespace, confusables).
- **Account edit "taken" checks** exclude the caller's own row (`id <> caller`): a case-only
  change (`Bob` → `bob`) is allowed and never answers `USERNAME_TAKEN` against itself. "Only
  when it differs" is a byte comparison in PHP; an identical value skips the check.

### 8. Brute-force protection

- **Login and register:** no application-level throttle in v1 (Citadel parity). The specs
  say so explicitly and point to the rate-limit Pending decision in `api/pending.md`, which
  is extended to name login, register and recover, leaning towards a **per-IP throttle**
  (failures per window → `429 TOO_MANY_REQUESTS`, client IP via `TRUSTED_PROXY_HOPS`). The
  deployment specs get a note recommending proxy-level rate limiting on `/auth/*.json`.
- Rejected: a per-username login lockout (lets anyone lock a victim out, and "locked"
  reveals that the username exists).
- **Recover cooldown:** if the user already has a password-reset token created less than
  `PASSWORD_RESET_COOLDOWN` seconds ago, no new token is created and no email is sent. The
  response (`200 { "sent": true }`) and the log line are the same, so it stays
  enumeration-safe. No new table (uses `password_reset_tokens.created_at`).
- New configuration row (decision 4's section): `PASSWORD_RESET_COOLDOWN`, default `60`
  seconds.
- #30 decides whether the admin send-recovery-email bypasses the cooldown (leaning: yes).

### 11. Edge cases

- **Concurrent register:** a unique-index violation on insert maps to `409 USERNAME_TAKEN` /
  `EMAIL_TAKEN` (username checked first, as in the pre-check) and rolls back the whole
  transaction, organization included.
- **Concurrent reset with the same token:** the token is consumed conditionally
  (`UPDATE … SET used_at = ? WHERE id = ? AND used_at IS NULL AND expires_at > ?`, `now`
  bound from PHP) in the same transaction as the password update; 0 rows → `400 Invalid or
  expired token`, nothing changed.
- **Lockout counting:** increments use an atomic upsert
  (`INSERT … ON DUPLICATE KEY UPDATE failed_attempts = failed_attempts + 1`), which also
  covers the first-insert race. An **expired lock** (`locked_until <= now`) is cleared
  (counter back to 0) before the attempt is evaluated, so one failure after expiry does not
  re-lock immediately.
- Password reset does **not** clear an account-edit lockout; account edit does **not** revoke
  sessions (unlike reset). Changing the email in account edit requires no verification of
  the new address.
- A `pending` user calling `PATCH /auth/account.json` gets the gate's `401` (only `approved`
  passes the gate). Recover works for `pending` users and the user stays `pending` after a
  reset.
- Logoff with an expired, rotated or otherwise revoked token still ends the session's active
  rows; an unknown token is a silent `204`.

## Solution

### 9. File layout and the old `/v1` session rows

```
api/endpoints.md            → short index (title, Purpose, table, Pending decisions)
api/endpoints/
  sending.md                → POST /v1/messages, moved unchanged
  auth.md                   → index of auth/*.md + shared rules (trimming, hashing, guards)
  auth/
    session.md              → register, login, refresh, logoff, status
    recovery.md             → recover, reset-password, reset tokens, recovery email
    account.md              → PATCH /auth/account.json + lockout
  admin.md                  → temporary home of the old admin table (#30/#31)
```

Old admin table:

- `POST /v1/registration`, `POST /v1/session`, `DELETE /v1/session` — **removed**, replaced by
  register, login and logoff.
- `GET /v1/session` — **removed**: user state comes from `POST /auth/status.json`, the
  organization from `GET /organization.json` (#31).
- Organization, `smtp_connections`, `api_tokens` rows and the "send test email" Pending
  decision — **moved** to `endpoints/admin.md`, keeping the superseded note; #31 rewrites it.
  The admin user management Pending decision stays there, pointing to #30.

Cross-references updated by #28: the `api.md` row; `api/authentication.md` (link to
`endpoints/auth.md`); `organizations/bootstrap.md` (registration spec → `auth/session.md`);
"#28" mentions in `refresh-tokens.md`, `sessions.md` and `gate.md` become links where useful.
`frontend/screens.md` (`GET /v1/session`) is left to #32.

Backward compatibility: none in practice — specs only, no PHP implements the old `/v1/session`
routes, and the frontend already calls `/auth/*.json`.

### 10. Scope

In scope (specs only, no PHP, migrations or frontend changes):

- The `api/endpoints/` layout of decision 9, with full contracts for register, login,
  refresh, logoff, status, recover, reset-password, password-reset tokens, the recovery
  email and `PATCH /auth/account.json` with its lockout: request, field rules, success status
  and body, side effects, error cases.
- The configuration rows of decisions 4 and 8, the `validation.md` bytes template
  (decision 6), the `USER_APPROVAL_REQUIRED` changes (decision 5a), the resolved/new Pending
  decisions, and the cross-reference updates of decision 9.

Out of scope:

- Device authorization (`/auth/authorization-requests*`, #29).
- Admin user endpoints, including admin recovery link / send-recovery-email (#30).
- Organization, SMTP connection, API token and test-email endpoints, and the rewrite of
  `endpoints/admin.md` (#31).
- Frontend changes (`organizationName` on the register form, `frontend/screens.md`; #32).
- The setup flow that marks the system organization and creates the first admin.

### 12. Security considerations

- Timing: dummy digest on login (decision 6); the recover latency leak is a documented known
  limitation (decision 3).
- Enumeration: login, recover and status follow the Citadel rules; register is the
  documented exception (decision 5b).
- The system organization is trusted like the admin (decision 2).
- Open registration is an SSRF vector, hence `USER_APPROVAL_REQUIRED=true` by default
  (decision 5a).
- No application-level login throttle in v1 (decision 8).
- Reset tokens and refresh tokens are stored only as SHA-256 hashes; plain values and
  passwords are never logged.
- The security agent reviews the endpoint specs.

### 13. Testing strategy

Specs only: verified by review (product-owner writes, security reviews). The specs must state
testable rules for the later implementation, including: the dummy-digest drift test
(decision 6), the 72-byte boundary, the recover cooldown and enumeration-identical responses,
the concurrent reset and lockout races, and `USER_APPROVAL_REQUIRED` parsing (unset, `false`,
typo).

### Open questions (recorded as Pending decisions)

- How the system organization is marked (setup flow).
- Outbox table + worker for asynchronous system email.
- Per-IP throttling of login, register and recover (`api/pending.md`).
- Username character set.
- Email verification flow.

## Benefits

- The backend can implement the auth endpoints against a complete, Pidgey-specific contract
  that the copied frontend already speaks.
- Security trade-offs (timing, enumeration, SSRF via open registration, system email trust,
  brute force) are explicit and either resolved or recorded as Pending decisions.
- The endpoint specs get a structure (`api/endpoints/`) that #29–#31 extend instead of
  rewriting.

## Depends on

"API conventions", "Users & organizations data model", "Authentication model".

### Reference: Citadel contract (verbatim excerpts)

Excerpts from "Citadel API — endpoint contract for a reimplementation". Links point to the Citadel source tree and are kept for traceability only.

#### 4. Auth endpoints

Source: [`auth/auth.controller.ts`](/Users/darthjee/projetos/orca/citadel/backend/src/auth/auth.controller.ts),
[`auth/auth.service.ts`](/Users/darthjee/projetos/orca/citadel/backend/src/auth/auth.service.ts),
[`auth/token.service.ts`](/Users/darthjee/projetos/orca/citadel/backend/src/auth/token.service.ts).

##### 4.1 `POST /auth/register.json`

Public. Creates an account and logs it in right away.

Request:

```json
{ "username": "bob", "email": "bob@example.com", "password": "s3cretpass" }
```

| Field | Rules |
| --- | --- |
| `username` | string, not empty |
| `email` | valid email |
| `password` | string, min length 8 |

Success: **`201`**, [shared user payload](#36-shared-user-payload), and the `access_token` cookie is set.

Side effects:

1. Check that `username` and `email` are free (one query matching either). If a row matches,
   answer `409` with `USERNAME_TAKEN` when its username matches (checked first), otherwise
   `EMAIL_TAKEN`. Messages: `username is not available` / `email is not available`. Denied
   users' rows are kept, so their username and email stay taken.
2. Hash the password with bcrypt (cost 10).
3. Insert the user with `is_admin = false`, and `status = pending` or `approved` depending on
   `USER_APPROVAL_REQUIRED`.
4. Issue tokens: start a new session (see 3.2).

The response is the same for pending and approved users.

| Case | Answer |
| --- | --- |
| Validation failure | `400 VALIDATION_FAILED` |
| Username / email taken | `409 USERNAME_TAKEN` / `EMAIL_TAKEN` |

##### 4.2 `POST /auth/login.json`

Public.

Request:

```json
{ "username": "bob", "password": "s3cretpass" }
```

| Field | Rules |
| --- | --- |
| `username` | string, not empty |
| `password` | string, not empty |

Success: **`201`**, [shared user payload](#36-shared-user-payload), and the `access_token` cookie is set. A new session starts.

| Case | Answer |
| --- | --- |
| Unknown username or wrong password (any status) | `401 UNAUTHORIZED` — `Invalid username or password` |
| Correct password, user `denied` | `403 USER_DENIED` — `This account has been denied access`; no tokens, no cookie |
| Correct password, user `pending` | `201`, the same as approved (the gate blocks the session until approval) |
| Correct password, user `approved` | `201` |

The password is always compared with bcrypt, **even when the user doesn't exist** (against a
fixed dummy bcrypt digest), so response time doesn't reveal whether the username exists. The
status is checked only **after** the password, so it cannot be probed without the password.

##### 4.3 `POST /auth/refresh.json`

Public. Rotates the refresh token.

Request:

```json
{ "refreshToken": "9f2c…" }
```

| Field | Rules |
| --- | --- |
| `refreshToken` | string, not empty |

Success: **`201`**, [shared user payload](#36-shared-user-payload) with the **new** refresh token, and a new `access_token` cookie is set (same `sid`).

Checks, in order:

| # | Case | Answer |
| --- | --- | --- |
| 1 | Hash not found, or `expires_at` in the past | `401 Invalid or expired refresh token` |
| 2 | Revoked with `rotated` | Replay detection: revoke every active token of the user with `replay_detected`, then `401 Invalid or expired refresh token` |
| 3 | Revoked with any other reason | `401 Invalid or expired refresh token` |
| 4 | User row missing | `401 Invalid refresh token` |
| 5 | User `denied` | Revoke the presented token with `admin_denied`, then `401 Invalid or expired refresh token` (no replay detection) |
| 6 | User `pending` or `approved` | Rotate atomically (see 3.2). Losing a concurrent rotation → `401 Invalid or expired refresh token` |

##### 4.4 `DELETE /auth/logoff.json`

Public. Ends the whole session.

Request (JSON **body** on `DELETE`):

```json
{ "refreshToken": "9f2c…" }
```

Success: **`204 No Content`**, empty body, and the `access_token` cookie is cleared.

Side effects: look up the token by hash, **whatever its state** (a stale, already-rotated
token still works). Then revoke every active row with the same `user_id` and `session_uuid`
with reason `logout`. Other sessions of the user are untouched. An unknown token is a silent
no-op that still answers `204`. The behaviour is the same for every user status.

| Case | Answer |
| --- | --- |
| Missing / empty `refreshToken` | `400 VALIDATION_FAILED` |
| Anything else | `204` |

##### 4.5 `POST /auth/status.json`

Public. A **passive** "am I logged in?" check, called when the frontend mounts. It never
rotates, never runs replay detection and never touches the cookie, so a second tab's check
can't log out the first tab.

Request:

```json
{ "refreshToken": "9f2c…" }
```

Success: **always `201`** when the body is valid.

| Caller | Response body | Side effect |
| --- | --- | --- |
| Active token, user `approved` | `{ "loggedIn": true, "isAdmin": true }` (or `false`) | none |
| Active token, user `pending` | `{ "loggedIn": false, "isAdmin": false, "status": "pending" }` | none (session kept) |
| Active token, user `denied` | `{ "loggedIn": false, "isAdmin": false }` | revoke the presented token with `admin_denied` |
| Unknown, revoked or expired token; or user row missing | `{ "loggedIn": false, "isAdmin": false }` | none |

"Active" means the hash was found, `revoked_at` is null and `expires_at > now`. The denied
answer is byte for byte the anonymous one. The pending answer is the only `loggedIn: false`
answer for which the frontend keeps the session and shows a "waiting for approval" state.

##### 4.6 `POST /auth/recover.json`

Public. Starts a self-service password reset.

Request:

```json
{ "email": "bob@example.com" }
```

| Field | Rules |
| --- | --- |
| `email` | valid email |

Success: **always `200`**, `{ "sent": true }`, whether or not the email exists.

Side effects:

- User found with status `approved` or `pending`: create a password-reset token (see
  [4.9](#49-password-reset-tokens)) and send the recovery email **asynchronously**
  (fire-and-forget). A mail failure is logged and never reaches the response.
- Unknown email, or user `denied`: do nothing (no token, no email).

The status, body and log line are the same in both cases, so the endpoint can't be used to
learn which emails are registered. Response time is not equalised.

##### 4.7 `POST /auth/reset-password.json`

Public. Completes the reset with the token taken from the email link.

Request (the frontend also sends `password_confirmation`, which is ignored):

```json
{ "token": "a1b2…(96 hex chars)", "password": "newpass123" }
```

| Field | Rules |
| --- | --- |
| `token` | string, not empty |
| `password` | string, min length 8 |

Success: **`200`**, `{ "reset": true }`.

| Case | Answer |
| --- | --- |
| Token hash not found, already used, or expired | `400 BAD_REQUEST` — `Invalid or expired token` |
| Valid token, but user missing or `denied` | the same `400`. The check happens **before** hashing the new password, and the token is **not** consumed |
| Valid | update `password_digest` (bcrypt cost 10), set the token's `used_at = now`, revoke every active refresh token of the user with `password_reset`; `200` |

A `pending` user stays `pending` after a reset. It is never `401`, so the frontend doesn't try
to refresh.

##### 4.8 `PATCH /auth/account.json`

Requires a logged-in user (gate). Updates the caller's own username, email and/or password.
The current password is always required.

Request:

```json
{
  "currentPassword": "s3cretpass",
  "username": "bobby",
  "email": "bobby@example.com",
  "newPassword": "evenmoresecret"
}
```

| Field | Rules |
| --- | --- |
| `currentPassword` | string, not empty (required) |
| `username` | optional; string, not empty |
| `email` | optional; valid email |
| `newPassword` | optional; string, min length 8 |

Success: **`200`**, `{ "username": "bobby", "email": "bobby@example.com" }` (the values after the update).

Steps, in order:

1. None of `username` / `email` / `newPassword` present (or all empty) → `400 BAD_REQUEST` —
   `At least one of username, email, or newPassword is required`. This does **not** count as a
   failed attempt.
2. Caller locked out (see below) → `423 LOCKED` — `Account temporarily locked due to too many failed attempts`.
3. Load the caller. If the row is missing → `401 Invalid session`.
4. These checks each **count as a failed attempt** when they fail:
   - wrong `currentPassword` → `400 BAD_REQUEST` — `Invalid current password`;
   - new `username` (only when it differs from the current one) used by another user →
     `409 USERNAME_TAKEN` — `Username already in use`;
   - new `email` (same rule) used by another user → `409 EMAIL_TAKEN` — `Email already in use`.
5. Apply the changes (a new password is hashed with bcrypt, cost 10) and **reset** the failure
   counter.

Changing the password here does **not** revoke sessions (unlike reset-password).

**Lockout** (table `auth_account_edit_lockouts`, one row per user, upserted): each failure adds
1 to `failed_attempts`. When the count reaches `ACCOUNT_EDIT_MAX_ATTEMPTS` (default 5),
`locked_until = now + ACCOUNT_EDIT_LOCK_MS` (default 5 min). While `locked_until > now`, every
call answers `423`. A success resets the row to `failed_attempts = 0, locked_until = null`.
Handle the race on first insert: if the insert hits the unique `user_id`, re-read the row and
increment it.

##### 4.9 Password-reset tokens

The same mechanism is used by recover, admin recovery-link and admin send-recovery-email
([`auth/password-reset.service.ts`](/Users/darthjee/projetos/orca/citadel/backend/src/auth/password-reset.service.ts)):

- Token: 48 random bytes, hex (96 chars), stored as a SHA-256 hex hash in
  `auth_password_reset_tokens`.
- Lifetime: `PASSWORD_RESET_TOKEN_TTL_MS`, default **30 minutes**.
- Single use (`used_at`). Issuing a new token does **not** invalidate the user's other
  outstanding tokens.
- Link: `{FRONTEND_BASE_URL}/#/recover-password?token={token}`.
- Email, template `password-recovery`
  ([`mail/templates/password-recovery/`](/Users/darthjee/projetos/orca/citadel/backend/src/mail/templates/password-recovery/)),
  plain text:
  - Subject: `Reset your <App name> password`
  - Body: a greeting, the `{{resetUrl}}` link, and a note that the link is single-use, expires
    shortly, and can be ignored if the user didn't ask for it.
- When outbound email is disabled by configuration, sending is a logged no-op that reports
  "not sent".
