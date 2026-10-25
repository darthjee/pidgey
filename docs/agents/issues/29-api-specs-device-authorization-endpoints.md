# Issue: API specs: device-authorization endpoints

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

Specify the device-authorization flow (Citadel contract §5) under `docs/agents/specs/api`,
adapted to Pidgey's conventions and to the rules already set by #27 and #28. The frontend
(`AccountsClient`, `AuthorizationRequestPoller`, `AuthorizationRequests` page) already calls
this exact contract, so no frontend change is expected.

## Problem

The specs have the `authorization_requests` table and the `TRUSTED_PROXY_HOPS` setting, but no
contract for the device-authorization endpoints the frontend already calls
(`/auth/authorization-requests*`). Several references still point to "#29", and Citadel's
contract uses millisecond settings, inconsistent expiry boundaries and a password cap that
conflict with the conventions set by #25–#28.

## Expected Behavior

##### 1. File layout

The paths are `/auth/*`, so the spec lives under the auth endpoints folder created by #28:

- `api/endpoints/auth/device-authorization.md`: index, with the state machine, the
  conditional transitions, lazy expiry, and the shared rules (guards, trimming, expiry
  boundary, clock, error answers).
- `api/endpoints/auth/device-authorization/requester.md`:
  `POST /auth/authorization-requests.json` (create) and
  `POST /auth/authorization-requests/{uuid}/poll.json` (poll and claim).
- `api/endpoints/auth/device-authorization/approver.md`:
  `POST /auth/authorization-requests/mine.json`,
  `POST /auth/authorization-requests/{uuid}/authorize.json` (per-row lockout) and
  `POST /auth/authorization-requests/{uuid}/deny.json`.
- `api/conventions/client-ip.md` (new): client IP and user-agent resolution, shared with the
  future per-IP throttle (see `api/pending.md`). Linked from `api/conventions.md`.
- `api/endpoints/auth.md`: add the `device-authorization.md` row to its index and replace the
  "specified separately (#29)" sentence. `api/endpoints.md` mentions device authorization in
  the `auth.md` row.

##### 2. Configuration

Added to the Authentication section of `architecture/configuration.md`. Durations are in
**seconds** and use `_TTL`/`_WINDOW` names (no `_MS`, no Citadel prefix):

| Setting | Default | Effect |
| --- | --- | --- |
| `AUTHORIZATION_REQUEST_TTL` | `3600` (1 h) | Lifetime of a device request. |
| `AUTHORIZATION_REQUEST_CREATE_LIMIT` | `5` | Creates allowed per IP and, separately, per username within the window. |
| `AUTHORIZATION_REQUEST_CREATE_WINDOW` | `60` | Rate-limit window, in seconds. |
| `AUTHORIZATION_REQUEST_MAX_OPEN_PER_USER` | `5` | Open requests per user; the oldest is expired to make room. |
| `AUTHORIZATION_REQUEST_AUTHORIZE_MAX_ATTEMPTS` | `5` | Wrong passwords before the per-request lockout. |
| `AUTHORIZATION_REQUEST_AUTHORIZE_LOCK_TTL` | `300` (5 min) | Per-request lockout duration. |

- `TRUSTED_PROXY_HOPS` (already listed) is a count that must allow `0`: add an explicit
  exception to the Rules ("must be a **non-negative** integer; any other value refuses
  boot").
- Remove the sentence "Settings for device authorization (#29) are added by that spec."

##### 3. Client IP (`api/conventions/client-ip.md`)

- `TRUSTED_PROXY_HOPS = 0`: `X-Forwarded-For` is ignored; the IP is `REMOTE_ADDR`.
- `h > 0` and the header is present: split it on `,`, trim each entry, take the entry at
  index `max(0, len − h)`.
- Missing header, or the selected entry is not a valid IPv4/IPv6 address
  (`filter_var(..., FILTER_VALIDATE_IP)`): fall back to `REMOTE_ADDR`.
- The IP is stored up to 45 characters; the user agent is the `User-Agent` header (or `""`),
  truncated to 512 characters (on a UTF-8 character boundary).
- Warning kept: with `h` larger than the real number of proxies, clients can spoof their IP.

##### 4. Contract alignment with #28

- Every device-auth endpoint is `POST`: the origin check applies to all of them (public ones
  included); `mine`, `authorize` and `deny` are gated (only `approved` users pass, uniform
  `401` otherwise).
- Success codes keep Citadel's **`201`** on every endpoint (create, poll, mine, authorize,
  deny), as #28 did for login, refresh and status.
- `username` (create) is **trimmed** before validation (not empty after trimming, max 255
  characters) and stored trimmed; the lookup relies on the column collation, like login.
- `password` (authorize) is a password that is only **checked**: a non-empty string, never
  trimmed, **no max length** (Citadel's 128 cap is dropped), as `currentPassword` in #28.
- `pollToken`: non-empty string, taken as sent.
- A malformed `{uuid}` path segment is **not** a validation error: the route accepts any
  segment, and the lookup simply finds nothing (`404` on poll, the uniform `400` on authorize
  and deny).
- Error answers: poll `404 NOT_FOUND` — `Authorization request not found`; authorize
  `400 BAD_REQUEST` — `Unable to authorize this request`; deny `400 BAD_REQUEST` —
  `Unable to deny this request`. Body validation failures stay `400 VALIDATION_FAILED`.
- The winning poll returns the shared `user` payload, the `refreshToken`, and sets the
  `access_token` cookie exactly like login.

##### 5. Expiry boundary and clock

- A request is expired when **`now >= expires_at`**, everywhere (poll, mine, authorize).
  Citadel mixes `<` and `>`; Pidgey uses one boundary.
- `now` is PHP's UTC clock, bound as a parameter (see `authentication/sessions.md#clock`).
- Deny does **not** check expiry: an expired-but-still-`open` row can be denied.

##### 6. Claim atomicity

The winning poll runs in **one transaction**: the conditional update
`approved → logged` (`logged_at = now`), the user lookup and status check, then either the
`logged → denied` flip (`resolved_at = now`, answer `{status: "denied"}`) or the session
insert (first refresh token). If the session insert fails, the transaction rolls back, so the
row is never left `logged` without credentials having been issued (the error is a `500`, and
a later poll can claim it again).

##### 7. Authorize lockout

Same mechanics as the account-edit lockout of #28, on the request row:

- The request row is held with `SELECT … FOR UPDATE` from the status/expiry checks through
  the password verification to the increment or reset, so parallel attempts cannot run more
  password checks than the limit.
- The bcrypt compare **always** runs once the row is found, owned and open: against the
  caller's digest, or the hard-coded `DUMMY_DIGEST` if the caller row is missing.
- If `authorize_locked_until > now`: answer `400`, counter **not** incremented, even with the
  right password.
- An expired lock is cleared before the attempt is evaluated (count restarts from zero).
- A wrong password increments `authorize_failed_attempts` (committed although the request
  fails); when the incremented value is **`>=`** `AUTHORIZATION_REQUEST_AUTHORIZE_MAX_ATTEMPTS`,
  `authorize_locked_until = now + AUTHORIZATION_REQUEST_AUTHORIZE_LOCK_TTL`.
- Success: `status = approved`, `approved_by_user_id = caller`, `resolved_at = now`, counter
  reset.
- The lockout is **per request row**, not per user; it does not interact with the
  account-edit lockout.

##### 8. Security: known limitations

Documented in the spec as known limitations:

- **Device-login denial of service:** anyone can create 5 requests a minute with a victim's
  username. The per-username limit then makes the victim's own requests unsaved (their poll
  always gets `404`), and the open cap evicts the victim's oldest open request. Password login
  is not affected.
- **Timing:** create runs extra queries (open-cap check, eviction) when the username exists,
  so its timing can reveal existence. Accepted; the body and status never do.
- **Approval phishing:** an attacker's request for a victim's username shows up in the
  victim's "Authorizations" list. The approver UI shows the request IP, user agent and
  creation time; approving still requires the password.
- Over-limit creates are not saved, so they do not count towards the limit.

##### 9. Pending decisions

- A short **verification code** shown on both devices and checked by the approver (in the
  style of RFC 8628 `user_code`), against approval phishing.
- **Retention** of `authorization_requests` rows (they keep IP and user agent forever): e.g.
  a purge command for resolved/expired rows older than N days, to be decided with the
  operational endpoints/commands (#31).

#### Edge cases

- Create for an unknown username: a row with `user_id = null` is saved (when under the
  limit); it can never be approved, and polls answer `open` until it expires.
- Create while over the limit: the payload is returned but nothing is saved; polls get `404`.
- Create for a `pending` or `denied` user: a row is saved; the user cannot pass the gate to
  approve it, and a claim would answer `denied`.
- Open cap reached: the oldest `open` row (by `created_at`, then `id`) is flipped to `expired`
  with a conditional update; the new request is always inserted.
- Poll with the right uuid and a wrong token, or a wrong uuid: same `404`.
- Poll of an `open` row at exactly `expires_at`: flipped to `expired`.
- Two polls race on an `approved` row: one wins credentials, the other gets `logged`.
- Claim when the user was denied or set back to pending after approval: `denied`, no session.
- Claim when the user row was deleted: `logged`, no credentials.
- Authorize on an expired, already approved, denied, logged or foreign row: uniform `400`,
  no password check counted.
- Authorize while locked with the right password: `400`, counter unchanged.
- Deny of an expired-but-`open` row: succeeds.
- `mine` never lists expired rows, even if still `open` in the table.
- Session created by a claim behaves like a login session (rotation, replay detection,
  logoff, revocation).

## Solution

Write the files of decision 1 and update the cross-references below.

#### Scope

In scope (specs only):

- The new files listed in decision 1 and the cross-reference updates:
  - `api/endpoints/auth.md`, `api/endpoints.md`, `api/conventions.md`;
  - `architecture/configuration.md` (settings, `TRUSTED_PROXY_HOPS` rule, drop the #29
    sentence);
  - `api/authentication/gate.md` (the shared `user` payload sentence pointing to "#29");
  - `api/authentication.md` and `overview/terminology.md` (point "Authorization request" to
    the new spec);
  - `organizations/data-model/authorization-requests.md` (re-point "#27 / #29" to the new
    spec; no column change);
  - `api/authentication/local-development.md` (link to `conventions/client-ip.md`);
  - `api/pending.md` (the per-IP throttle uses `conventions/client-ip.md`).

Out of scope:

- PHP code, migrations, frontend changes.
- Admin endpoints for device requests (none in Citadel).
- An application-level throttle on login/register (still a pending decision from #28).

#### Testing (for the implementation issue)

The spec lists testable rules, including:

- Enumeration safety of create: same status and body shape for an existing/unknown username
  and over/under the limit.
- Both rate-limit counts always run; the limit uses `>=`.
- Client-IP resolution table: hops `0`, `1`, `2`; header missing; entries with spaces; an
  invalid entry falling back to `REMOTE_ADDR`.
- Every state transition is conditional (a concurrent second actor loses).
- Claim transaction rollback when the session insert fails.
- Authorize lockout: threshold, locked-with-right-password, expired lock resets the counter,
  `DUMMY_DIGEST` compare when the caller row is missing.
- Config validation: positive integers for the new settings, non-negative for
  `TRUSTED_PROXY_HOPS`.

#### Open questions

None: everything not settled is recorded under decision 9.

## Benefits

- The backend implementation of device authorization has a complete, Pidgey-consistent
  contract matching what the frontend already sends and expects.
- Client-IP resolution is specified once and reusable by the future per-IP throttle.
- Known abuse cases are documented instead of discovered in production.

## Depends on

"API conventions", "Users & organizations data model" (authorization requests table),
"Authentication model", "Auth endpoints" (#28, merged: `api/endpoints/auth/` exists).

### Reference: Citadel contract

Excerpts from "Citadel API — endpoint contract for a reimplementation". Links point to the Citadel source tree and are kept for traceability only.

#### 5. Device-authorization endpoints

A second way to log in. A device that is **not** logged in (the *requester*) asks to log in as
`username`. A device where that user **is** logged in (the *approver*) sees the request,
confirms it with its own password, and approves it. The requester polls until it gets a
session.

Source: [`auth/authorization-request.controller.ts`](/Users/darthjee/projetos/orca/citadel/backend/src/auth/authorization-request.controller.ts),
[`auth/authorization-request.service.ts`](/Users/darthjee/projetos/orca/citadel/backend/src/auth/authorization-request.service.ts),
[`auth/authorization-request-abuse-guard.service.ts`](/Users/darthjee/projetos/orca/citadel/backend/src/auth/authorization-request-abuse-guard.service.ts).

##### 5.1 State machine

```
                 authorize               winning poll
   ┌──────┐   ─────────────▶ ┌──────────┐ ───────────▶ ┌────────┐
   │ open │                  │ approved │              │ logged │
   └──────┘                  └──────────┘              └────────┘
     │  │                                                  │
deny │  │ poll after expires_at,                           │ requester not approved
     │  │ or evicted by the open-cap                       │ (pending/denied) at claim
     ▼  ▼                                                  ▼
 ┌────────┐  ┌─────────┐                              ┌────────┐
 │ denied │  │ expired │                              │ denied │
 └────────┘  └─────────┘                              └────────┘
```

- Every transition is a **conditional update** (`WHERE uuid = ? AND status = <from>`), so
  concurrent actors can't both win.
- Expiry is **lazy**: nothing flips rows in the background. An `open` row past `expires_at` is
  flipped to `expired` by the poll that sees it, and `authorize` / `deny` refuse it.

##### 5.2 `POST /auth/authorization-requests.json`

Public. Creates a request.

Request:

```json
{ "username": "bob" }
```

| Field | Rules |
| --- | --- |
| `username` | string, not empty, max length 255 |

Success: **`201`**:

```json
{
  "uuid": "3f1e7a52-8c1b-4d0e-9a51-2b7f0c9d1e44",
  "pollToken": "c0ffee…(96 hex chars)",
  "expiresAt": "2026-10-25T13:00:00.000Z"
}
```

Behaviour:

1. Look up the user by `username` (it may not exist).
2. Compute the rate limit: count requests created within the last `CREATE_WINDOW_MS`
   (default 60 s) with the **same IP** and, separately, with the **same username**. Always run
   **both** counts (in parallel, no short-circuit). The caller is over the limit if either
   count is ≥ `CREATE_LIMIT` (default 5).
3. Generate `uuid` (v4), `pollToken` (48 random bytes, hex) and
   `expiresAt = now + AUTHORIZATION_REQUEST_TTL_MS` (default 1 h).
4. **Over the limit** → return that payload anyway, **without** saving anything. The poll
   token will then always get a `404`.
5. If the user exists, enforce the **open cap**: if they already have ≥ `MAX_OPEN_PER_USER`
   (default 5) `open` rows, flip their **oldest** `open` row to `expired` (`resolved_at = now`).
   The new request is never rejected.
6. Insert the row: `status = open`, `user_id` = the user's id or **null** when the username
   doesn't exist (such a row can never be approved), `poll_token_hash = sha256(pollToken)`,
   `request_ip`, `request_user_agent`.
7. Return the payload.

**Enumeration safety:** the response looks the same whether or not the username exists and
whether or not the caller is over the limit.

Client IP: taken from `X-Forwarded-For`, trusting `TRUSTED_PROXY_HOPS` hops (default 1). With
hops `h` and the list `a, b, c`, the IP is the entry at index `max(0, len − h)`. Without the
header, use the socket address. User agent: the `User-Agent` header, or `""`. Store up to 45
chars for the IP and 512 for the user agent.

##### 5.3 `POST /auth/authorization-requests/{uuid}/poll.json`

Public. Called by the requester on an interval.

Request:

```json
{ "pollToken": "c0ffee…" }
```

| Field | Rules |
| --- | --- |
| `pollToken` | string, not empty |

Find the row by `uuid` **and** `poll_token_hash = sha256(pollToken)`.

| Case | Answer |
| --- | --- |
| No row (unknown uuid **or** wrong token, indistinguishable) | `404 NOT_FOUND` — `Authorization request not found` |
| `open` and `expires_at < now` | flip to `expired` (`resolved_at = now`); `201 { "status": "expired" }` |
| `open` (not expired) | `201 { "status": "open" }` |
| `denied` / `expired` / `logged` | `201 { "status": "<that status>" }` |
| `approved` | try to **claim** it (below) |

**Claim** (`approved` → `logged`, conditional, `logged_at = now`):

- Lost the race (0 rows affected) → `201 { "status": "logged" }`, no credentials.
- Won, but `user_id` is null or the user row is missing → `201 { "status": "logged" }`.
- Won, but the user is not `approved` (pending or denied) → move `logged` → `denied`
  (`resolved_at = now`) and answer `201 { "status": "denied" }`, with no session and no
  cookie. This is the same answer as an explicit deny.
- Won, user `approved` → start a new session and answer **`201`** with the `access_token`
  cookie set:

```json
{
  "status": "approved",
  "user": { "id": 7, "username": "bob", "email": "bob@example.com", "isAdmin": false },
  "refreshToken": "9f2c…"
}
```

Only the first poll that claims the request gets credentials. Every later poll (including the
requester's own next tick) gets `{ "status": "logged" }`.

##### 5.4 `POST /auth/authorization-requests/mine.json`

Requires a logged-in user (gate). Lists the caller's own pending requests, so the approver UI
can show them. Empty body.

Success: **`201`**:

```json
{
  "requests": [
    {
      "uuid": "3f1e7a52-8c1b-4d0e-9a51-2b7f0c9d1e44",
      "requestIp": "203.0.113.9",
      "requestUserAgent": "Mozilla/5.0 …",
      "createdAt": "2026-10-25T12:00:00.000Z",
      "expiresAt": "2026-10-25T13:00:00.000Z"
    }
  ]
}
```

Filter: `user_id = caller`, `status = 'open'`, `expires_at > now`, newest first
(`created_at DESC`).

##### 5.5 `POST /auth/authorization-requests/{uuid}/authorize.json`

Requires a logged-in user (gate). Approves a request raised against the caller's **own**
username, after re-checking the caller's password.

Request:

```json
{ "password": "s3cretpass" }
```

| Field | Rules |
| --- | --- |
| `password` | string, not empty, max length 128 |

Success: **`201`**, `{ "authorized": true }`.

Every business rejection answers the **same** `400 BAD_REQUEST` —
`Unable to authorize this request`:

1. Row not found, `user_id ≠ caller`, or `status ≠ open`.
2. `expires_at < now`.
3. Then always run the bcrypt compare against the caller's digest (or the dummy digest if the
   caller row is missing), so timing stays the same:
   - if the row is **locked out** (`authorize_locked_until > now`) → `400`, and the counter is
     **not** incremented;
   - else if the password is wrong → increment `authorize_failed_attempts`. When it reaches
     `AUTHORIZE_MAX_ATTEMPTS` (default 5), set
     `authorize_locked_until = now + AUTHORIZE_LOCK_MS` (default 5 min). Answer `400`.
4. Success: `status = approved`, `approved_by_user_id = caller`, `resolved_at = now`, reset the
   counter (`authorize_failed_attempts = 0`, `authorize_locked_until = null`).

The lockout is **per request row**, not per user.

##### 5.6 `POST /auth/authorization-requests/{uuid}/deny.json`

Requires a logged-in user (gate). Empty body. No password needed.

Success: **`201`**, `{ "denied": true }`. Sets `status = denied`, `resolved_at = now`.

Row not found, `user_id ≠ caller`, or `status ≠ open` → `400 BAD_REQUEST` —
`Unable to deny this request`. Expiry is **not** checked here: an expired-but-still-`open` row
can be denied.

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

| Setting | Default | Effect |
| --- | --- | --- |
| `AUTHORIZATION_REQUEST_TTL_MS` | `3600000` (1 h) | Device request lifetime. |
| `AUTHORIZATION_REQUEST_CREATE_LIMIT` | `5` | Per-IP / per-username creates per window. |
| `AUTHORIZATION_REQUEST_CREATE_WINDOW_MS` | `60000` | Rate-limit window. |
| `AUTHORIZATION_REQUEST_MAX_OPEN_PER_USER` | `5` | Open-request cap (oldest evicted). |
| `AUTHORIZATION_REQUEST_AUTHORIZE_MAX_ATTEMPTS` | `5` | Wrong passwords before the per-request lockout. |
| `AUTHORIZATION_REQUEST_AUTHORIZE_LOCK_MS` | `300000` (5 min) | |
| `ACCOUNT_EDIT_MAX_ATTEMPTS` | `5` | Failed account edits before the per-user lockout. |
| `ACCOUNT_EDIT_LOCK_MS` | `300000` (5 min) | |
| `TRUSTED_PROXY_HOPS` | `1` | How many `X-Forwarded-For` hops to trust for the client IP. |
