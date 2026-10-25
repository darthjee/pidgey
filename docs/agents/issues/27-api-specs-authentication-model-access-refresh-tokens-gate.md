# Issue: API specs: authentication model (access/refresh tokens, gate)

## Description

Sub-issue of the epic #24 (Backend endpoint specs: enhance `docs/agents/specs/api` with the
Citadel contract). **Specs only**: no PHP code, migrations or frontend changes.

Rewrite `docs/agents/specs/api/authentication.md` (split into `api/authentication/` when it grows
too big, following the granularity rule in `docs/agents/specs.md`) with Pidgey's authentication
model, adapted from Citadel's contract:

- a JWT access token in the `access_token` cookie;
- refresh-token sessions with rotation and replay detection;
- the per-request gate and the admin check;
- user status rules and the shared `user` payload;
- Bearer API tokens for the public `/v1` API.

Also add a configuration spec and remove the PHP-session wording that #27 owns.

Depends on #25 (conventions: error body, status rules, origin check), which is merged, and #26
(users and organizations data model: `users`, `refresh_tokens`, `api_tokens`), which is merged.

Decisions inherited from the epic:

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

The current `api/authentication.md` (29 lines) describes a classic PHP session for the admin UI
and an `admin` Bearer scope for the admin endpoints. The frontend copied from Citadel instead
uses a JWT `access_token` cookie plus refresh tokens on root-relative `/auth/*.json` paths. The
data model from #26 already defines `refresh_tokens`, `users.status`/`is_admin` and
`api_tokens.scopes`, and points to `api/authentication.md` for the rules, but those rules do not
exist yet. Configuration is only a three-line note in `architecture/backend.md`.

## Expected Behavior

### Goal (from the epic)

Rewrite `docs/agents/specs/api/authentication.md` (split into a folder if it grows too big)
with the authentication model:

- Access token: JWT HS256 signed with `SECRET_KEY`, key rotation with `PREVIOUS_SECRET_KEYS`,
  claims, `access_token` cookie attributes, never in the body.
- Refresh tokens and sessions: format, 7-day validity, SHA-256 at rest, session chains,
  atomic rotation, revocation reasons, replay detection.
- The per-request gate (one DB query, identical `401`), the admin check, user status rules,
  the shared `user` payload.
- Bearer API tokens with scopes for the public `/v1` API (keep the existing rules, adapted).
- Configuration settings for authentication (secret keys, TTLs, `USER_APPROVAL_REQUIRED`,
  `ALLOWED_ORIGINS`, `FRONTEND_BASE_URL`, `TRUSTED_PROXY_HOPS`), in the spec where Pidgey
  documents configuration.
- Local development note: the cookie is `Secure`, so dev needs HTTPS or a terminating proxy.
- Remove the PHP-session parts that no longer apply (also in `organizations/bootstrap.md`
  if not already done by the data model sub-issue).
- Security agent reviews the result.

### Credentials: cookie session vs. Bearer tokens

The two credentials are **disjoint**:

- **Cookie session** (`access_token` JWT plus refresh token) is the only credential for the admin UI
  surface: `/auth/*.json`, `/admin/users/*.json`, and Pidgey's organization, SMTP connection, API
  token and test-email endpoints. Order of checks: origin check → gate → admin check (admin
  routes only).
- **Bearer API token** is the only credential for the public `/v1` API (`POST /v1/messages`).
  Only the scope check applies; there is no origin check.
- The **`admin` scope is removed**. In v1 the only scope is `send`. `api_tokens.scopes` stays a
  JSON array, so scopes can be added later.
- A Bearer token presented on a cookie route is ignored (the gate answers `401`). A cookie
  presented on a `/v1` route is ignored as well (missing Bearer → `401`).
- Kept from the current spec: the token format (CSPRNG, `pdg_` prefix, SHA-256 at rest, shown
  once), `revoked_at` and `last_used_at`, `401` for a missing, invalid or revoked token, and `403`
  for a missing scope. Token expiration and finer-grained scopes stay Pending decisions.
- Follow-ups for other sub-issues: the `Auth:` line of `api/endpoints.md` changes (#31). The token
  creation screen (`frontend/screens.md`, "choose name and scopes") has only `send` to offer
  (#32).

### Configuration

- **New spec `architecture/configuration.md`:** one table of every environment variable (DB,
  SMTP, log, auth, origin) with its default and effect. The Configuration section of
  `architecture/backend.md` becomes a pointer to it. The auth spec links to the rows it uses.
- **Bare names, durations in seconds**, matching the existing `DB_*` / `SMTP_*` variables (read
  through `Pidgey\Infra\Env`):

| Setting | Default | Effect |
| --- | --- | --- |
| `SECRET_KEY` | required | Signs access-token JWTs (HS256). |
| `PREVIOUS_SECRET_KEYS` | empty | Comma-separated retired keys; used only to verify. |
| `ACCESS_TOKEN_TTL` | `900` | JWT `exp` and the cookie `Max-Age`, in seconds. |
| `REFRESH_TOKEN_TTL` | `604800` (7 days) | Refresh-token lifetime, in seconds. Configurable, unlike Citadel's hard-coded value. |
| `USER_APPROVAL_REQUIRED` | `false` | Exactly `true` → new registrations start `pending`. |
| `ALLOWED_ORIGINS` | empty | Origin check and CORS (see `api/conventions/origin-check.md`). |
| `FRONTEND_BASE_URL` | empty | Fallback trusted origin; base of links in emails. |
| `TRUSTED_PROXY_HOPS` | `1` | How many `X-Forwarded-For` hops to trust for the client IP. |

- **`SECRET_KEY` rules:** the app refuses to boot when it is missing or shorter than 32 bytes. Each
  `PREVIOUS_SECRET_KEYS` entry follows the same rule. `.env` ships a clearly fake development
  key.
- This settles the Pending decision in `origin-check.md` about variable names (bare
  `ALLOWED_ORIGINS` and `FRONTEND_BASE_URL`).
- **Only #27's rows are added here.** #28 and #29 add their own settings (password reset TTL,
  device-request limits, account-edit lockout) to the same table.

### Local development (`Secure` cookie)

- **No TLS in development, and no switch that turns off `Secure`.** The `access_token` cookie has the
  same attributes in every environment (`HttpOnly; Secure; SameSite=Strict; Path=/`).
- This works because browsers treat `http://localhost` as a secure context: Chrome and Firefox
  accept `Secure` cookies there. Dev uses `http://localhost:8030` (Vite, proxying to `app:80`) or
  `http://localhost:8080` (the app directly).
- The spec records these caveats:
  - Open dev through `localhost`, not a LAN IP or a custom hostname.
  - Safari may reject the cookie over plain http, so use Chrome or Firefox in dev.
  - Production must be served over HTTPS, either directly or behind a TLS-terminating proxy
    (then `TRUSTED_PROXY_HOPS` applies).
- The note goes in the authentication spec. `frontend/development.md` links to it.
- **Follow-up for #32 (infra/frontend):** the Vite dev proxy only forwards `/v1` today. The
  Citadel paths (`/auth/*.json`, `/admin/users/*.json` and Pidgey's admin endpoints) must also be
  proxied, and the origin check must accept the proxied dev origin.

### JWT implementation (alternatives)

- **JWT plus refresh tokens instead of the PHP session:** decided in the epic (#24), because the
  copied frontend already speaks this contract. Not reopened here.
- **Hand-rolled HS256, no library.** About 60 lines using `base64url`, `hash_hmac('sha256')` and
  `hash_equals`. The dependency policy (`architecture/dependencies.md`: runtime is `php` and
  `monolog` only) stays unchanged. `firebase/php-jwt` and `lcobucci/jwt` were considered and
  rejected: each adds a runtime dependency to track for advisories, for a single fixed algorithm.
- The spec pins the strict rules. Any failure is the uniform gate `401`:
  - Signing: a fixed header `{"alg":"HS256","typ":"JWT"}`, always with the current `SECRET_KEY`.
  - Exactly three segments, strict base64url (no padding, no other alphabet).
  - The header must say exactly `alg: HS256`. `none` and every other algorithm are rejected, and
    `kid`, `jku`, `x5u` and similar headers are never honored.
  - The signature is compared with `hash_equals`: first against the current key, then against each
    `PREVIOUS_SECRET_KEYS` entry.
  - The payload must be a JSON object: `sub` an integer, `sid` a well-formed UUID, `iat`/`exp`
    integers, and `exp > now`. No other claim is trusted. `username`/`isAdmin` are informational
    only and are always read from the DB.
- The security agent reviews these rules.

### Edge cases

- **Concurrent refresh (multi-tab race): keep Citadel's strict rules.**
  - Rotation is atomic, and when the conditional revoke loses, the request rolls back and answers
    `401`.
  - Re-presenting a `rotated` token is always treated as a replay, with no grace window. A race
    between tabs can therefore revoke every session of the user. The spec states this explicitly.
  - **Follow-up for #32:** check that the copied frontend runs a single refresh at a time, across
    tabs (single-flight).
- **Clock: PHP clock, UTC, no leeway.**
  - Every time comparison (JWT `exp`, `refresh_tokens.expires_at`, `issued_at`, `started_at`)
    uses PHP's clock in UTC.
  - SQL receives `now` as a bound parameter and never calls `NOW()`, so PHP and MySQL timezones or
    clocks cannot disagree.
  - The JWT check is `exp > now` with no leeway, since one server both signs and verifies.
- **Key rotation:** dropping a key from `PREVIOUS_SECRET_KEYS` only invalidates access tokens
  signed with it. The client then refreshes (refresh tokens are not signed with the key), so
  rotation never logs anyone out unless the refresh token has also expired. The spec describes
  the rotation procedure:
  1. Move the current key into `PREVIOUS_SECRET_KEYS`.
  2. Set the new `SECRET_KEY`.
  3. Remove the old key after at least `ACCESS_TOKEN_TTL`.
- **Status and admin changes take effect on the next request** (Citadel 3.3), because the gate
  reads the DB: logout, password reset, replay detection, deny, approve and demotion.
- **Pending decision: cleanup of `refresh_tokens`.** Nothing purges rows in v1. Only rows past
  `expires_at` are safe to delete, because revoked-but-unexpired `rotated` rows are needed for
  replay detection. A future purge command must follow that rule.

### Backward compatibility: leftover PHP-session text

Nothing is implemented yet (specs only), so there is no data or client to migrate. The only work is
to remove text that contradicts the new model.

| File | Leftover | Handled by |
| --- | --- | --- |
| `api/authentication.md` | "Admin UI session: classic PHP session cookie" | **#27** (rewritten) |
| `docker/compose.md` | "session endpoints" in the `app` row | **#27** (wording) |
| `overview/components.md` | "(session cookie)", "admin session endpoints" | **#27** |
| `docs/agents/architecture.md` | "admin session endpoints" | **#27** |
| `.claude/agents/security.md` | "session cookie flags… CSRF on session-authenticated endpoints" | **#27**: the focus becomes the JWT access cookie, refresh rotation and replay detection, and the origin check |
| `api/endpoints.md` | `/v1/session`, `/v1/registration`, "PHP session cookie, or Bearer token with scope `admin`" | #27 adds only a one-line "auth superseded, see authentication.md" note. **#28** and **#31** rewrite the endpoints. |
| `frontend/screens.md`, `frontend/layout.md` | "PHP session cookie; no tokens stored", `GET /v1/session` | **#32** |

`organizations/bootstrap.md` was already handled by #26.

### Scope

In scope (specs only):

- `api/authentication.md`, split into `api/authentication/` if it grows too big. It covers the access
  token and its strict HS256 rules, refresh tokens and sessions (rotation, revocation reasons,
  replay detection), the gate, the admin check, user status rules at the gate, the shared `user`
  payload, Bearer API tokens (scope `send` only), key rotation, and the local-development note.
- New `architecture/configuration.md`, with the auth and origin rows. `architecture/backend.md`'s
  Configuration section points to it.
- `api/conventions/origin-check.md`: resolve the variable-name Pending decision.
- The leftover wording in the Backward compatibility table owned by #27, plus the superseded note
  in `api/endpoints.md`.
- `overview/terminology.md`: the `Scope` example drops `admin`.
- `frontend/development.md`: link to the local-development note.

Out of scope:

- Endpoint payloads, error codes and rate limits for `/auth/*` (#28), device authorization (#29),
  `/admin/users/*` (#30) and Pidgey admin endpoints (#31).
- Frontend changes, the Vite proxy paths and the single-flight refresh check (#32).
- PHP code, migrations and `.env` changes (implementation issues after the epic).

### Security considerations

- The **security agent reviews** the rewritten auth spec, in particular:
  - the strict JWT rules;
  - the gate (one query, a byte-identical `401`, DB errors → `500`);
  - atomic rotation and replay detection;
  - cookie attributes;
  - the disjoint credentials;
  - the `SECRET_KEY` rules.
- The gate answers the same `401` body for every failure, so callers cannot tell why a token was
  rejected. Timing differences between "bad signature" and "no DB row" are accepted, because the
  token is not secret to its holder.
- Refresh-token storage in the browser follows the Citadel contract: the frontend keeps it and
  sends it in bodies. That makes it readable by XSS, which is why the frontend XSS posture
  matters. This is recorded as a Pending decision in the spec and checked in #32.

### Testing strategy

Specs only, so there are no automated tests. Acceptance:

- The security agent's review has no open findings.
- No spec still describes the PHP session for the parts #27 owns.
- Every new or changed spec file has its `Purpose:` line and a **Pending decisions** section.

### Pending decisions to record

- Finer-grained Bearer scopes and token expiration: still Pending (carried over).
- Cleanup of `refresh_tokens`: Pending (see Edge cases).
- Refresh-token storage in the browser (XSS exposure): Pending; to be revisited with #32.

In Citadel's text, table names `auth_users` and `auth_refresh_tokens` map to Pidgey's `users` and
`refresh_tokens` (#26). Citadel's `*_TTL_MS` settings become the second-based settings above.

## Solution

The product-owner agent writes the specs:

1. Rewrite `api/authentication.md` as an index of `api/authentication/`, with files for:
   - access token (JWT rules, cookie, key rotation);
   - refresh tokens and sessions (rotation, revocation reasons, replay detection, multi-tab
     race);
   - gate and admin check (guard order, uniform `401`, `403`, status rules, `user` payload);
   - Bearer API tokens (`send` only, disjoint from the cookie);
   - local development.
2. Add `architecture/configuration.md` and point `architecture/backend.md` to it. Resolve the
   variable-name Pending decision in `origin-check.md`.
3. Fix the leftover wording #27 owns:
   - `docker/compose.md`;
   - `overview/components.md`;
   - `docs/agents/architecture.md`;
   - `overview/terminology.md`;
   - `.claude/agents/security.md`;
   - the superseded note in `api/endpoints.md`;
   - the link from `frontend/development.md`.
4. The security agent reviews the result.

## Benefits

- The backend auth model matches the copied frontend, so #28–#32 can specify the endpoints on a
  settled base.
- The strict JWT rules, uniform gate and replay detection are written down before any code
  exists, and reviewed by security.
- A single configuration reference replaces scattered env-var mentions.

### Reference: Citadel contract (verbatim excerpts)

Excerpts from "Citadel API — endpoint contract for a reimplementation". Links point to the Citadel source tree and are kept for traceability only.

#### 3. Authentication model

There are two credentials: a short-lived **access token** in a cookie, and a long-lived
**refresh token** that the frontend stores and sends in request bodies.

##### 3.1 Access token (cookie `access_token`)

- A JWT signed with **HS256** using the application secret key (`SECRET_KEY`). When verifying,
  retired keys from `PREVIOUS_SECRET_KEYS` are tried after the current key, so a key can be
  rotated without logging everyone out. Signing always uses the current key.
- Claims:

| Claim | Type | Meaning |
| --- | --- | --- |
| `sub` | integer | User id. |
| `sid` | UUID string | Session id, which is the `session_uuid` of the refresh-token chain (see 3.2). |
| `username` | string | Informational only; the backend never trusts it. |
| `isAdmin` | boolean | Informational only; the backend never trusts it. |
| `iat`, `exp` | integer | Standard; `exp = iat + ACCESS_TOKEN_TTL` (default 15 min). |

- Cookie attributes: `HttpOnly`, `Secure`, `SameSite=Strict`, `Path=/`,
  `Max-Age = ACCESS_TOKEN_TTL` (default 900 s, the same value as the JWT lifetime).
- The token is **never** put in a response body. The frontend cannot read it, which is why
  `isAdmin` is returned in the JSON payloads.
- Logout clears the cookie (`Set-Cookie: access_token=; Path=/; Expires=<past>`).

##### 3.2 Refresh token and sessions

- An opaque random string: **48 random bytes, hex-encoded (96 chars)**. Valid for **7 days**.
- It is returned in the JSON body of login, register, refresh and the winning device poll. The
  frontend stores it and sends it as `{ "refreshToken": "..." }`.
- It is **stored only as a SHA-256 hex hash** (`token_hash`). The plain value is never stored.
- A **session** is a chain of rotated refresh tokens that share one `session_uuid` and one
  `started_at`.
  - Login, register and a winning device poll each **start a new session** (new UUID,
    `started_at = now`).
  - Refresh **rotates**: it inserts a new token row with the same `session_uuid`/`started_at`
    and revokes the presented one with reason `rotated`. Both writes happen in **one
    transaction**, and the revoke is conditional (`WHERE id = ? AND revoked_at IS NULL`). If the
    conditional revoke affects 0 rows, a concurrent refresh already won: roll back and answer
    `401`. A session therefore never forks and has at most one active row.
- Revocation always sets `revoked_at` **and** `revoked_reason` together:

| Reason | Written by |
| --- | --- |
| `rotated` | Refresh, on the presented token. |
| `logout` | Logoff, on every active token of the presented token's session. |
| `password_reset` | Reset-password, on every active token of the user. |
| `replay_detected` | Refresh, when a `rotated` token is presented again: every active token of the user. |
| `admin_denied` | Admin deny (every active token of the user); refresh/status of a denied user (the presented token). |

- **Replay detection:** presenting a token already revoked with `rotated` means it was stolen
  or replayed, so every active token of that user is revoked with `replay_detected`. Any other
  revoked reason is a plain `401` with no side effect.
- Bulk revokes only touch active rows (`revoked_at IS NULL`), so already-revoked rows keep
  their original reason.

##### 3.3 The per-request gate

Every non-public route runs this check
([`core/jwt.guard.ts`](/Users/darthjee/projetos/orca/citadel/backend/src/core/jwt.guard.ts),
[`auth/session.service.ts`](/Users/darthjee/projetos/orca/citadel/backend/src/auth/session.service.ts)):

1. No `access_token` cookie → `401 "Missing access token"`.
2. Verify the signature and expiry (current key, then previous keys).
3. `sid` must be a well-formed UUID and `sub` an integer.
4. Find the user through **one DB query**: `auth_users` joined to `auth_refresh_tokens` on
   `user_id`, where `session_uuid = sid`, `revoked_at IS NULL`, `expires_at > now`, and
   `user.status = 'approved'`. A row must exist and its user id must equal `sub`.
5. The authenticated user is `{ id: sub, sid, username, isAdmin }`, with `username`/`isAdmin`
   taken **from the DB row**, not from the JWT.

Every failure in steps 2–4 answers the **same** `401`, byte for byte apart from the timestamp:

```json
{ "error": { "code": "UNAUTHORIZED", "message": "Invalid or expired access token" }, "statusCode": 401, "timestamp": "..." }
```

A DB error in step 4 is a `500`, never a `401`.

Because of step 4, these take effect on the **next request**, not when the access token
expires: logout (that session), password reset, replay detection and admin deny (every session
of the user), admin approve (a pending user's existing token starts working), and admin
demotion (admin routes start answering `403`).

##### 3.4 Admin check

Admin routes additionally need the DB `is_admin = true`. An authenticated non-admin gets
`403 { "code": "FORBIDDEN", "message": "Admin access required" }`. An anonymous caller gets
`401` from the gate first.

Guard order for every request: **origin check → gate (non-public routes) → admin check (admin
routes) → body validation → handler.**

##### 3.5 User status

`auth_users.status` is one of `pending`, `approved`, `denied`.

- Only `approved` users pass the gate.
- New registrations start `pending` when `USER_APPROVAL_REQUIRED=true`, otherwise `approved`.
- `pending` users can log in and get a real session (tokens issued), but the gate blocks
  every protected route until an admin approves them. `status.json` tells the frontend they are
  pending.
- `denied` users look like unknown users everywhere, except a login with the **correct**
  password, which answers `403 USER_DENIED`.

##### 3.6 Shared `user` payload

Login, register, refresh and the winning device poll all return:

```json
{
  "user": { "id": 7, "username": "bob", "email": "bob@example.com", "isAdmin": false },
  "refreshToken": "9f2c…(96 hex chars)"
}
```

and set the `access_token` cookie. `status` and the password digest are **never** included
here.

#### 9. Configuration

Names below are shown without Citadel's `CITADEL_PLACEHOLDER_` prefix. Use whatever naming fits
the new app.

| Setting | Default | Effect |
| --- | --- | --- |
| `SECRET_KEY` | — (required) | Signs access-token JWTs (HS256). |
| `PREVIOUS_SECRET_KEYS` | empty | Comma-separated retired keys, accepted when verifying only. |
| `ACCESS_TOKEN_TTL_MS` | `900000` (15 min) | JWT `exp` and the cookie `Max-Age`. |
| Refresh-token TTL | 7 days (hard-coded) | |
| `PASSWORD_RESET_TOKEN_TTL_MS` | `1800000` (30 min) | |
| `USER_APPROVAL_REQUIRED` | `false` | Exactly `true` → new registrations start `pending`. |
| `AUTHORIZATION_REQUEST_TTL_MS` | `3600000` (1 h) | Device request lifetime. |
| `AUTHORIZATION_REQUEST_CREATE_LIMIT` | `5` | Per-IP / per-username creates per window. |
| `AUTHORIZATION_REQUEST_CREATE_WINDOW_MS` | `60000` | Rate-limit window. |
| `AUTHORIZATION_REQUEST_MAX_OPEN_PER_USER` | `5` | Open-request cap (oldest evicted). |
| `AUTHORIZATION_REQUEST_AUTHORIZE_MAX_ATTEMPTS` | `5` | Wrong passwords before the per-request lockout. |
| `AUTHORIZATION_REQUEST_AUTHORIZE_LOCK_MS` | `300000` (5 min) | |
| `ACCOUNT_EDIT_MAX_ATTEMPTS` | `5` | Failed account edits before the per-user lockout. |
| `ACCOUNT_EDIT_LOCK_MS` | `300000` (5 min) | |
| `TRUSTED_PROXY_HOPS` | `1` | How many `X-Forwarded-For` hops to trust for the client IP. |
| `FRONTEND_BASE_URL` | — | Base of the reset links; CORS/CSRF trusted origin fallback. |
| `ALLOWED_ORIGINS` | empty | Comma-separated bare origins for CORS + the origin check; `*` (dev only) reflects any origin. |
| Email settings | disabled | Toggle, SMTP host/port/user/password/TLS, `From` address, timeout. |
