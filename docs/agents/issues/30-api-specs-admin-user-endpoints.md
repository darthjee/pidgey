# Issue: API specs: admin user endpoints

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

The admin frontend (`AdminClient`, Admin Users page, Admin User Edit page) already calls
Citadel's `/admin/users/*.json` contract, but Pidgey's specs have no admin user endpoints:
`api/endpoints/admin.md` only lists them as a pending decision, and `organizations/entities.md`
and `api/endpoints/auth/recovery.md` carry open "#30" decisions (limiting the admin's indirect
access, cooldown bypass on send-recovery-email).

## Expected Behavior

Write `docs/agents/specs/api/endpoints/admin-users.md` (linked from the `api/endpoints.md`
index) with the admin user-management contracts:

- Admin user item shape and `{id}` handling.
- `POST /admin/users/search.json`, `.../{id}/edit.json`, `.../{id}/approve.json`,
  `.../{id}/deny.json`, `.../{id}/recovery-link.json`, `.../{id}/send-recovery-email.json`.
- Pidgey adaptation: `is_admin` is a global instance admin; decide whether the admin user
  item includes the user's organization (e.g. `organizationId`/name), or mark pending.

All the decisions below are written into the specs, the "#30" pending entries are resolved,
and the new pending decisions are recorded.

## Solution

### Layout

- `docs/agents/specs/api/endpoints/admin-users.md`: the index. It covers the guards, the
  admin user item, `{id}` handling, and the shared rules (trimming, "taken" checks, operator
  log).
- `admin-users/review.md`: `POST /admin/users/search.json`, `.../{id}/approve.json` and
  `.../{id}/deny.json`.
- `admin-users/edit.md`: `POST /admin/users/{id}/edit.json`.
- `admin-users/recovery.md`: `.../{id}/recovery-link.json` and
  `.../{id}/send-recovery-email.json`.
- Cross-references:
  - link `admin-users.md` from `api/endpoints.md`;
  - remove the "admin user management" pending entry from `api/endpoints/admin.md`;
  - resolve the "#30" pending entries in `api/endpoints/auth/recovery.md` (cooldown bypass)
    and `organizations/entities.md` (indirect access);
  - replace the "#30" mentions in `entities.md` with links to the new specs;
  - add the `admin_edit` revocation reason to `api/authentication/sessions.md`;
  - register any new specific code in `api/conventions/errors.md`.

### Guards

Every route goes through: origin check (all are `POST`), then the gate (only `approved`
users), then the admin check (`users.is_admin = true` as read by the gate; otherwise
`403 FORBIDDEN` "Admin access required"), then body validation, then the handler. The review
tools (status filter, approve, deny) are always available, whatever `USER_APPROVAL_REQUIRED`
says. The caller id always comes from the session, never from the request.

### Admin user item

Citadel's item plus the user's organization, as flat `camelCase` fields (the frontend ignores
extra fields):

```json
{
  "id": 7,
  "username": "bob",
  "email": "bob@example.com",
  "isAdmin": false,
  "createdAt": "2026-10-17T12:00:00.000Z",
  "status": "pending",
  "organizationId": 3,
  "organizationName": "Bob's Org"
}
```

- `createdAt` is ISO 8601 UTC with milliseconds.
- Never included: the password digest, tokens, `updatedAt`, or any SMTP connection or API
  token data.

### `{id}` path parameter

- Anything that does not match `^[1-9][0-9]*$` → `400 BAD_REQUEST`,
  `Validation failed (numeric string is expected)`. This runs after the admin check and
  before body validation.
- Well-formed but unknown, or out of range for the column → `404 NOT_FOUND`,
  `User not found`.

### Alignment with the #28 auth rules

- **Trimming:** `username` and `email` are trimmed before validation. `newPassword` and `q`
  rules are below. Unknown fields are dropped, including `isAdmin`, `status` and
  `organizationId` sent to edit.
- An optional field that is absent or `null` is "not present". A present but invalid value is
  `400 VALIDATION_FAILED`.
- **Edit field rules:**

  | Field | Rules |
  | --- | --- |
  | `username` | optional; string, not empty after trimming, max 255 characters |
  | `email` | optional; valid email after trimming, max 255 characters |
  | `newPassword` | optional; min 8 characters, max 72 bytes, no NUL byte, never trimmed |

  The new password is hashed as in `auth.md`.
- **"Taken" checks:** the same as account edit. A value is checked only when it differs
  byte-wise from the current one. The query excludes the target's own row and matches with
  the column collation. A unique-index violation on update (a race) applies nothing and
  answers the same `409 USERNAME_TAKEN` / `EMAIL_TAKEN`.
- **Success codes** follow Citadel. Search, edit, recovery-link and send-recovery-email
  answer `201`; approve and deny answer `200`.

### Search

- `q`: optional string. It is trimmed, and empty after trimming means no filter. It is a
  case- and accent-insensitive substring match (column collation) on `username` **or**
  `email`. `%`, `_` and `\` are escaped, so `q` is always a literal substring. Max 255
  characters.
- `status`: optional, one of `pending`, `approved`, `denied`. `null` means absent; an invalid
  value is `400 VALIDATION_FAILED` (`status must be one of the following values: pending,
  approved, denied`).
- Both filters combine with AND. An empty body `{}` or no body at all returns every user.
- Results are ordered by `id` ascending and **not paginated**. Pagination stays in the
  existing `api/pending.md` decision.
- Response:
  `{ "users": [<admin user item>…], "currentUserId": <caller id> }`.

### Edit

- At least one of `username`, `email` or `newPassword` must be present; otherwise
  `400 BAD_REQUEST`, `At least one of username, email, or newPassword is required`.
- There is no current password and no lockout. An admin may edit their own row this way: an
  accepted consequence of the admin trust level.
- Never changes `isAdmin`, `status` or `organizationId`.
- In one transaction:
  1. apply the changes;
  2. when `email` or the password changes, mark every unused password-reset token of the
     target as used;
  3. when `email` or the password changes, **revoke every active refresh token of the target**
     with the new reason `admin_edit`. This is a deliberate divergence from Citadel, which
     does not revoke; the frontend is unaffected. A username-only change does neither.
- Success: `{ "user": <admin user item> }`, reloaded after the update.

### Approve

- Empty body. A conditional update (`WHERE id = ? AND status = 'pending'`); 0 rows changed →
  `422 USER_NOT_PENDING`, `Only pending users can be approved`, and nothing changes.
- Sessions are untouched: the user's existing session passes the gate on its next request.
- Answers `200 { "user": <admin user item> }`.
- `denied` is therefore final. Reinstating a denied user is a **pending decision**.

### Deny

- Empty body. Checks, in order: unknown id → `404`; target is the caller →
  `422 CANNOT_DENY_SELF`, `You cannot deny your own account`.
- In **one transaction**: set `status = denied`, then revoke every active refresh token of
  the user with `admin_denied` (a deliberate improvement over Citadel's two separate writes).
- Any current status, including already `denied`, is accepted (idempotent, `200`).
- Admins can deny other admins. The row is kept, so the username and email stay taken.
- Deny does not touch the user's device-authorization requests or reset tokens: the
  device-authorization claim and reset-password already re-check the user's status.

### Recovery link and recovery email

Shared rules:

- Tokens use the mechanism in `auth/recovery.md`: 96 hex characters, SHA-256 stored,
  `PASSWORD_RESET_TOKEN_TTL`, single use.
- A token can be issued whatever the target's status. Redeeming it fails for a `denied` user
  (`Invalid or expired token`).
- Issuing does **not** invalidate the target's other outstanding tokens.
- Both **bypass** `PASSWORD_RESET_COOLDOWN`, and do not count towards it beyond the
  `created_at` they write.

`recovery-link.json`:

- Answers `201 { "resetUrl": "{FRONTEND_BASE_URL}/#/recover-password?token=…" }`.
- When `FRONTEND_BASE_URL` is empty → `409 CONFLICT`, `Recovery links are not configured`,
  and no token is created.

`send-recovery-email.json`:

- When sending is impossible by configuration (no system organization, no active SMTP
  connection on it, or empty `FRONTEND_BASE_URL`): no token is created; answers
  `201 { "sent": false }`.
- Otherwise:
  1. create the token;
  2. send the recovery email (the same email, sender and recipient rules as `recovery.md`:
     the stored `users.email`) **synchronously**;
  3. answer `201 { "sent": true }`, or `{ "sent": false }` on any SMTP failure. The token is
     kept, unused, and expires.
- Never answers an `SMTP_*` code, `error.smtp` or a `500` because of mail. Failures are
  logged to the operator log by user id only.

### Limiting the admin's indirect access (resolves the `entities.md` pending decision)

- **v1:**
  - every successful admin action (edit, approve, deny, recovery-link,
    send-recovery-email) writes an **operator log line**: the admin's id, the target's id,
    the action, and for edit the names of the changed fields. It never includes values,
    passwords, tokens or links;
  - an admin-driven email or password change revokes the target's sessions (see
    [Edit](#edit)).
- **Kept for frontend parity:** the recovery link is shown to the admin. This is documented
  under the admin trust level in `entities.md`.
- **Pending decisions:**
  - notifying the old email address on an admin email change;
  - a persisted audit table of admin actions;
  - forbidding an email and password change in the same admin edit.

### Known limitations

- Two admins can deny each other and leave the instance with no admin. Recovery is out of
  band, as for the first admin (`organizations/bootstrap.md`).
- Search returns every matching user in one unbounded response.
- Send-recovery-email's response time includes the SMTP round trip, bounded by the send flow
  timeouts.

### Pending decisions to record

- Reinstating a denied user.
- Notifying the old email on an admin email change.
- A persisted admin audit table.
- Forbidding a combined email and password change in an admin edit.

### Scope

**In scope:** specs only, in the files above and their cross-references.

**Out of scope:**

- PHP code, migrations and frontend changes;
- creating or promoting admins (out of band);
- organization endpoints (#31);
- a send-recovery-email outbox (already pending in `recovery.md`).

### Edge cases

- `{id}` values: `0`, `-1`, `01`, `1.5`, `abc`, a huge number → `400` or `404` as defined.
- Edit with only `null` fields → "at least one" `400`.
- Edit with an identical username → no taken check and no update.
- Edit with a case-only username change → allowed.
- Edit sending `isAdmin` / `status` → dropped.
- Search with `q = "%"` or `"_"` → a literal match.
- Search with a whitespace-only `q` → no filter.
- Approve racing deny → exactly one wins, and approve answers `422` if deny won.
- Deny of self → `422`; deny of an already denied user → `200`.
- A non-admin `approved` caller → `403`; a `pending` caller → gate `401`.
- Recovery for a `denied` user → token issued, redemption refused.

### Testing strategy (testable rules for the specs)

- **Guards:** guard order, `403` for a non-admin, and `401` before `400` for an anonymous
  caller with a bad body.
- **`{id}`:** the `400`/`404` boundary.
- **Search:**
  - LIKE escaping;
  - the AND combination;
  - ordering by id;
  - `currentUserId`;
  - organization fields in the item.
- **Edit:**
  - the "at least one" case;
  - the taken checks, including the race;
  - token invalidation and `admin_edit` revocation on an email or password change, and none
    on a username-only change;
  - 72-byte and NUL rejection.
- **Approve:** the conditional update, `422` on a non-pending user, sessions untouched.
- **Deny:**
  - self-deny `422`;
  - idempotence;
  - revocation in the same transaction;
  - every session rejected by the gate afterwards.
- **Recovery:**
  - `409` with an empty `FRONTEND_BASE_URL`;
  - cooldown bypass;
  - `sent:false` with no token when unconfigured;
  - `sent:false` with the token kept on an SMTP failure;
  - no `SMTP_*` codes;
  - other tokens left valid.
- **Operator log:** one line per admin action, never containing values, tokens or links.

## Benefits

- The backend can implement the admin user endpoints against a complete contract that the
  copied frontend already speaks.
- Admin power is bounded and traceable: session revocation on admin credential changes and
  an operator log of every admin action.
- Consistent with the #28 auth rules (trimming, password limits, "taken" checks, enumeration
  and SMTP handling).

## Depends on

"API conventions", "Users & organizations data model", "Authentication model". Uses the
password-reset tokens defined by "Auth endpoints" (excerpt included below). If the
`api/endpoints/` index does not exist yet, create it.

### Reference: Citadel contract (verbatim excerpts)

Excerpts from "Citadel API — endpoint contract for a reimplementation". Links point to the Citadel source tree and are kept for traceability only.

A DB error in step 4 is a `500`, never a `401`.

Because of step 4, these take effect on the **next request**, not when the access token
expires: logout (that session), password reset, replay detection and admin deny (every session
of the user), admin approve (a pending user's existing token starts working), and admin
demotion (admin routes start answering `403`).

#### 3.4 Admin check

Admin routes additionally need the DB `is_admin = true`. An authenticated non-admin gets
`403 { "code": "FORBIDDEN", "message": "Admin access required" }`. An anonymous caller gets
`401` from the gate first.

Guard order for every request: **origin check → gate (non-public routes) → admin check (admin
routes) → body validation → handler.**

#### 4.9 Password-reset tokens

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

### 6. Admin endpoints

Every route needs a logged-in user (gate) with `is_admin = true` (see 3.4). The review tools
(status filter, approve, deny) are always available, whatever `USER_APPROVAL_REQUIRED` says.

Source: [`auth/admin.controller.ts`](/Users/darthjee/projetos/orca/citadel/backend/src/auth/admin.controller.ts),
[`auth/admin.service.ts`](/Users/darthjee/projetos/orca/citadel/backend/src/auth/admin.service.ts).

#### 6.1 Admin user item

Every admin route that returns a user uses this shape. It is the only place `status` and
`createdAt` are exposed:

```json
{
  "id": 7,
  "username": "bob",
  "email": "bob@example.com",
  "isAdmin": false,
  "createdAt": "2026-10-17T12:00:00.000Z",
  "status": "pending"
}
```

The password digest, tokens and `updatedAt` are never included.

The `{id}` path parameter:

- not an integer → `400 BAD_REQUEST` — `Validation failed (numeric string is expected)`;
- no such user → `404 NOT_FOUND` — `User not found`.

#### 6.2 `POST /admin/users/search.json`

Request (both fields optional; an empty body `{}` or no body at all is fine):

```json
{ "q": "bob", "status": "pending" }
```

| Field | Rules |
| --- | --- |
| `q` | optional string |
| `status` | optional; one of `pending`, `approved`, `denied` |

Success: **`201`**:

```json
{ "users": [ { "id": 7, "username": "bob", "email": "bob@example.com", "isAdmin": false, "createdAt": "…", "status": "pending" } ], "currentUserId": 1 }
```

- `q` matches `username` **or** `email`, case-insensitive substring (`LIKE %q%`).
- `status` filters by status. When both are given, they are combined with AND.
- With neither, return every user. Results are **not paginated**, and no order is guaranteed.
- `currentUserId` is the caller's own id, so the UI can hide "deny" on their own row.

| Case | Answer |
| --- | --- |
| Invalid `status` | `400 VALIDATION_FAILED` — `status must be one of the following values: pending, approved, denied` |

#### 6.3 `POST /admin/users/{id}/edit.json`

Updates another user's username, email and/or password. No current password is needed, and
there is no lockout.

Request:

```json
{ "username": "bobby", "email": "bobby@example.com", "newPassword": "resetbyadmin" }
```

| Field | Rules |
| --- | --- |
| `username` | optional; string, not empty |
| `email` | optional; valid email |
| `newPassword` | optional; string, min length 8 |

Success: **`201`**, `{ "user": <admin user item> }` (reloaded after the update).

| Case | Answer |
| --- | --- |
| None of the three fields present | `400 BAD_REQUEST` — `At least one of username, email, or newPassword is required` |
| Unknown id | `404 User not found` |
| New username / email (only when different from the current one) used by another user | `409 USERNAME_TAKEN` — `Username already in use` / `409 EMAIL_TAKEN` — `Email already in use` |

It never changes `isAdmin` or `status` (those fields are dropped if sent). Changing the
password does **not** revoke the user's sessions.

#### 6.4 `POST /admin/users/{id}/approve.json`

Empty body. Success: **`200`**, `{ "user": <admin user item> }` with `status: "approved"`.

| Case | Answer |
| --- | --- |
| Unknown id | `404 User not found` |
| User not `pending` | `422 USER_NOT_PENDING` — `Only pending users can be approved`; nothing changes |
| Otherwise | `200` |

Use a **conditional update** (`WHERE id = ? AND status = 'pending'`). If 0 rows change, answer
`422`, so an approve can never overwrite a concurrent deny. Sessions are untouched: the user's
existing session starts passing the gate on its next request, with no re-login.

#### 6.5 `POST /admin/users/{id}/deny.json`

Empty body. Success: **`200`**, `{ "user": <admin user item> }` with `status: "denied"`.

Checks, in order:

| Case | Answer |
| --- | --- |
| Unknown id | `404 User not found` |
| `id` = the caller's own id | `422 CANNOT_DENY_SELF` — `You cannot deny your own account`; nothing changes |
| Any status (including already `denied`) | set `status = denied`, then revoke every active refresh token of the user with `admin_denied`; `200` |

- Admins can deny other admins. Only self-deny is refused.
- The caller id comes from the authenticated session, never from the request.
- The two writes are not a transaction. Once the status is `denied`, the gate rejects every
  session anyway, and a retried deny revokes whatever is left.
- The row is kept, so the username and email stay taken.

#### 6.6 `POST /admin/users/{id}/recovery-link.json`

Empty body. Success: **`201`**:

```json
{ "resetUrl": "https://app.example.com/#/recover-password?token=a1b2…" }
```

Creates a fresh password-reset token (see 4.9) for that user, whatever their status, and
returns the link so the admin can pass it on by hand. Unknown id → `404`. Redeeming the link
for a denied user fails like an invalid token.

#### 6.7 `POST /admin/users/{id}/send-recovery-email.json`

Empty body. Success: **`201`**, `{ "sent": true }` or `{ "sent": false }`.

Creates a token the same way, then sends the `password-recovery` email **synchronously** and
reports the result. `sent: false` covers both "email disabled by configuration" and "send
threw an error". This endpoint never answers `500` because of mail. Unknown id → `404`.
