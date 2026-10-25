# Issue: API specs: Pidgey admin & operational endpoints

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

The Pidgey-only endpoints are not specified in the Citadel style yet:
`api/endpoints/admin.md` is a temporary placeholder (old `/v1` paths, superseded session/Bearer
auth), there is no spec for the SMTP connection test email or for the operational probes, and
`POST /v1/messages` does not state how it relates to the new conventions. The pending decision
on whether a denied user's organization keeps working API tokens must also be settled before
the send and API token endpoints ship.

## Expected Behavior

Write the full contracts for the Pidgey-specific endpoints, linked from the
`api/endpoints.md` index:

- `api/endpoints/pidgey-admin.md` (index of `pidgey-admin/*.md`): organization (read/update), SMTP connections (list,
  create, read, update, delete, set active, test email), API tokens (list, create — plain
  token returned once —, revoke), in Citadel style (`*.json` paths, `camelCase`, cookie
  auth through the gate, Citadel error body and status codes, many reads as `POST` when the
  body carries data). Decide the exact paths, request/response shapes and field rules,
  based on `email/smtp-connections.md` and `organizations/data-model.md` (SMTP password
  write-only, at most one active connection, organization scoping → `404` for other orgs).
  Decide whether these endpoints need `is_admin` or any approved user of the organization
  (equal access).
- `api/endpoints/sending.md` (existing file, kept): `POST /v1/messages` with Bearer token scope `send`, keeping its
  own `/v1` contract; state how it relates to the conventions (error body, field case).
- `api/endpoints/operational.md`: `GET /health.json`, `GET /ready.json`.
- Update `api/pending.md` (pagination, rate limits, size limits, idempotency, CORS) to match.

## Solution

### Decisions

#### 1. Layout

- `api/endpoints/pidgey-admin.md`: short index (shared rules: access, `{id}` parsing, operator
  log) of:
  - `pidgey-admin/organization.md`: read and update the organization;
  - `pidgey-admin/smtp-connections.md`: list, create, read, update, delete, activate, test
    email;
  - `pidgey-admin/api-tokens.md`: list, create, revoke.
- `api/endpoints/operational.md`: `GET /health.json`, `GET /ready.json`.
- The public sending API stays in the existing `api/endpoints/sending.md` (not renamed to
  `messages.md`, since it is already linked from many specs); it is completed (section 7).
- The temporary `api/endpoints/admin.md` is **removed**; every link to it (`endpoints.md`,
  `admin-users.md`, `api-tokens.md`, `scope.md`, …) points to `pidgey-admin.md` instead.

#### 2. Access

- Every endpoint goes through the gate (cookie session, `approved` user). **No admin check**:
  any approved user of the organization has the same access (equal access policy).
- An instance admin has this access over **their own organization only**, like any member.
- The organization is always the authenticated user's `organization_id` (read by the gate),
  never request input. Resources of another organization answer `404`, as if they did not
  exist.

#### 3. Paths and success codes

| Method & path | Purpose | Success |
| --- | --- | --- |
| `GET /organization.json` | Read the caller's organization | `200` |
| `PATCH /organization.json` | Update it | `200` |
| `GET /smtp-connections.json` | List connections | `200` |
| `POST /smtp-connections.json` | Create a connection | `201` |
| `GET /smtp-connections/{id}.json` | Read one | `200` |
| `PATCH /smtp-connections/{id}.json` | Update one | `200` |
| `DELETE /smtp-connections/{id}.json` | Delete one | `204` |
| `POST /smtp-connections/{id}/activate.json` | Make it the active connection | `200` |
| `POST /smtp-connections/{id}/test.json` | Send a test email through it | `201` |
| `GET /api-tokens.json` | List tokens | `200` |
| `POST /api-tokens.json` | Create a token (plain token returned once) | `201` |
| `DELETE /api-tokens/{id}.json` | Revoke a token | `204` |

- Reads with no body use `GET` (the "reads as `POST`" rule applies only when a body carries
  data).
- `{id}` not matching `^[1-9][0-9]*$` → `400 BAD_REQUEST` "Validation failed (numeric string
  is expected)" (as in the admin user endpoints). Unknown, out of range or another
  organization's id → `404 NOT_FOUND` `SMTP connection not found` / `API token not found`.
- Lists are ordered by `id` ascending, with no pagination (small, organization-scoped).

#### 4. Shapes and field rules

General rules, aligned with the auth and admin-user specs: strings are trimmed; absent or
`null` means "not given"; unknown fields are ignored; validation errors are
`400 VALIDATION_FAILED` with one `details` entry per failed rule.

**Organization** — `{ id, name, createdAt, updatedAt }`.

- `PATCH` body `{ name }`; `name` required, 1–255 characters after trimming.

**SMTP connection** — `{ id, name, host, port, encryption, username, hasPassword,
fromAddress, active, createdAt, updatedAt }`.

- `password` is **write-only**: never returned; `hasPassword` (boolean) says whether one is
  stored. Stored encrypted as in `email/smtp-connections.md`.
- Fields:
  - `name`: required, 1–255 characters.
  - `host`: required, 1–255 characters, a hostname or IP literal; no whitespace or control
    characters.
  - `port`: required integer, 1–65535.
  - `encryption`: required, one of `none`, `tls`, `ssl`.
  - `username`: optional, at most 255 characters, no control characters.
  - `password`: optional, at most 255 characters, no CR, LF or NUL.
  - `fromAddress`: required, a valid email address, at most 255 characters, no CR/LF
    (header injection).
- `username` and `password` are **both set or both empty**, checked on the resulting state
  (after a `PATCH` is merged) → `400 VALIDATION_FAILED` otherwise.
- `POST` (create): `active` is not accepted (ignored); a new connection always starts
  inactive.
- `PATCH`: a field absent or `null` is unchanged; `""` clears `username` / `password`
  (removing authentication). Required fields cannot be cleared.
- `DELETE` removes the row (hard delete).

**API token** — `{ id, name, tokenPrefix, scopes, createdAt, lastUsedAt, revokedAt }`.

- `POST` body `{ name, scopes }`:
  - `name`: required, 1–255 characters (not unique);
  - `scopes`: required, non-empty array of known scope names (v1: only `send`); unknown
    scopes → `400 VALIDATION_FAILED`; duplicates are removed.
- The create response (`201`) is the item plus `token`, the **plain token, returned once**;
  it is never returned again nor logged.
- The list includes revoked tokens (with `revokedAt` set).
- `DELETE` sets `revoked_at` (the row is kept); revoking an already revoked token answers
  `204` again (idempotent).

#### 5. Active connection

- A new connection always starts **inactive**; it becomes active only through `activate`.
- `activate` runs in **one transaction**: lock the organization row (`SELECT … FOR UPDATE`),
  set `active = false` on every other connection of the organization, set it on the target.
  Activating the already active connection answers `200`.
- Deleting the active connection is allowed; the organization then has no active
  connection and `POST /v1/messages` answers `409 NO_SMTP_CONNECTION`.
- **Pending:** a `deactivate` endpoint; a database-level constraint guaranteeing at most one
  active connection per organization.

#### 6. Test email

- `POST /smtp-connections/{id}/test.json`, body `{ to? }`: `to` is optional and defaults to
  the caller's own email; when given, a valid email address, at most 255 characters.
- Fixed subject and text body (e.g. "Pidgey test email"); no caller-supplied content.
- Synchronous, through the same send flow as `POST /v1/messages` (`email/send-flow.md`), but
  on the **given** connection, active or not, so a connection can be tested before it is
  activated.
- Success `201`: `{ "status": "sent", "smtp": { "code": 250, "message": "…" } }`, the same
  shape as the `POST /v1/messages` success body.
- Failure: the `SMTP_*` codes and `error.smtp` of `email/failures.md` (allowed, since the
  caller is authenticated). `NO_SMTP_CONNECTION` never applies.

#### 7. `POST /v1/messages`

- Keeps its own contract: `/v1` prefix, no `.json` suffix, Bearer token with scope `send`,
  success `200` (not `201`). These are documented exceptions to the conventions.
- Uses the shared error envelope; its fields (`to`, `subject`, `text`, `html`) are already
  `camelCase`. The origin check applies (server callers send no `Origin`).
- **Settles the pending "denied user vs API tokens" decision** (`organizations/entities.md`):
  a token whose organization has **no `approved` user** is refused at authentication with the
  same `401 UNAUTHORIZED` as a revoked token. No data changes (`revoked_at` stays unset), so
  the token works again if a user is approved (or a future reinstatement happens).

#### 8. Operational endpoints

- `GET /health.json`: liveness, always `200` `{ "status": "ok" }`, touches nothing.
- `GET /ready.json`: readiness, runs `SELECT 1`:
  - DB OK → `200` `{ "status": "ok", "checks": { "database": "up" } }`;
  - DB failing → `503` `{ "status": "error", "checks": { "database": "down" } }`, the reason
    logged, never returned.
- Public: no gate, no admin check; `GET`, so no origin check. No cache headers (no caching
  proxy).
- Wiring them into Docker / deployment health checks is out of scope.

#### 9. Operator log and security

- Every successful state change (organization update; connection create, update, delete,
  activate; test email; token create, revoke) writes an **operator log line**: user id,
  organization id, action, resource id and, for updates, the names of the changed fields.
  Never values of `password`, the plain token or its hash; the test email logs the outcome,
  not the recipient's address beyond what `send-flow.md` already logs.
- **Server-side request forgery (known limitation):** a user chooses the SMTP `host` and
  `port`, so the server can be made to open TCP connections to internal addresses (and the
  test email's error categories act as a reachability oracle). Any host stays allowed in v1,
  since self-hosted setups need internal relays and Mailpit. **Pending:** a configurable host
  deny-list / allow-list.

#### 10. `api/pending.md`

- **Pagination:** list endpoints (SMTP connections, API tokens) are unpaginated in v1; the
  pending item stays for future growth.
- **Rate limits:** add the test email endpoint to the rate-limits item.
- **SSRF:** add the SMTP host deny-list item (section 9).
- Size limits, idempotency and CORS are unchanged.

### Scope

In scope (specs only, under `docs/agents/specs`):

- the new files of section 1, and removing `api/endpoints/admin.md`;
- `api/endpoints/sending.md` (section 7), `api/endpoints.md` (index);
- `api/authentication/api-tokens.md` (organization without an approved user → `401`; links);
- `organizations/entities.md` (resolve the API-token pending decision), `organizations/scope.md`
  (links);
- `email/smtp-connections.md` (link to the endpoints, `hasPassword`, active rules);
- `api/conventions/errors.md` (only if a new specific code is introduced; none is planned);
- `api/pending.md` (section 10).

Out of scope:

- PHP code, migrations, frontend code;
- `frontend/screens.md` and the frontend contract (#32);
- Docker / deployment health-check wiring.

### Edge cases

- `{id}` `0`, negative, non-numeric, or beyond `BIGINT` → `400` / `404` as in section 3.
- Another organization's connection or token → `404`, same body as unknown.
- `PATCH` that clears `username` but not `password` (or the reverse) → `400`.
- `PATCH` with only `password` on a connection that has a username → allowed (rotates the
  password).
- Two concurrent `activate` calls on different connections → the organization lock
  serializes them; exactly one ends active.
- Deleting the active connection → no active connection; sending answers `409`.
- Test email on an inactive connection → allowed; on another organization's → `404`.
- Revoke an already revoked token → `204`.
- `scopes` empty, with an unknown name, or with duplicates → `400`, `400`, deduplicated.
- Token of an organization whose only user is `pending` or `denied` → `401` on `/v1`.
- `/ready.json` with the database down → `503`, no error envelope, reason logged.

### Testing

The future implementation must cover, at least:

- each endpoint's success code and body shape; `password` never present in any response;
  the plain token only in the create response;
- gate `401` for anonymous calls, before validation; no admin check (a non-admin approved
  user succeeds);
- organization isolation: `404` for every endpoint on another organization's resources;
- field validation per section 4, including the `username`/`password` pairing on merged
  state;
- activate atomicity (at most one active connection after concurrent activations);
- test email through Mailpit (success) and failure codes (unreachable host, wrong
  credentials);
- token revocation idempotency; `/v1/messages` refusing tokens of organizations without an
  approved user;
- `/health.json` and `/ready.json` with the database up and down;
- operator log lines present, with no secrets.

### Known limitations

- SSRF / internal reachability through user-chosen SMTP hosts (section 9).
- No rate limit on the test email endpoint (tracked in `api/pending.md`).
- Synchronous test email: a slow SMTP server holds the request up to the configured timeouts.
- Unpaginated lists.

## Benefits

- One consistent contract (paths, auth, errors, field rules) for every endpoint the admin UI
  calls, ready for the backend implementation.
- Organization isolation, write-only secrets and the active-connection rules are explicit and
  testable.
- The open "denied user vs API tokens" decision is closed before sending ships.

### Depends on

"API conventions", "Users & organizations data model", "Authentication model". If the
`api/endpoints/` index does not exist yet, create it.

### Reference: Citadel contract (verbatim excerpts)

Excerpts from "Citadel API — endpoint contract for a reimplementation". Links point to the Citadel source tree and are kept for traceability only.

#### 1. Overview

The API covers four areas:

| Area | Purpose |
| --- | --- |
| Auth | Register, log in, refresh, log out, check session status, recover/reset password, edit own account. |
| Device authorization | Log in on a new device by approving it from a device that is already logged in, instead of typing the password. |
| Admin | Search users, edit them, approve or deny registrations, and generate or send password-recovery links. |
| Operational | Liveness and readiness probes. |

Endpoint summary (21 routes):

| Method & path | Auth | Success |
| --- | --- | --- |
| `POST /auth/register.json` | public | `201` |
| `POST /auth/login.json` | public | `201` |
| `POST /auth/refresh.json` | public | `201` |
| `DELETE /auth/logoff.json` | public | `204` |
| `POST /auth/status.json` | public | `201` |
| `POST /auth/recover.json` | public | `200` |
| `POST /auth/reset-password.json` | public | `200` |
| `PATCH /auth/account.json` | user | `200` |
| `POST /auth/authorization-requests.json` | public | `201` |
| `POST /auth/authorization-requests/{uuid}/poll.json` | public | `201` |
| `POST /auth/authorization-requests/mine.json` | user | `201` |
| `POST /auth/authorization-requests/{uuid}/authorize.json` | user | `201` |
| `POST /auth/authorization-requests/{uuid}/deny.json` | user | `201` |
| `POST /admin/users/search.json` | admin | `201` |
| `POST /admin/users/{id}/edit.json` | admin | `201` |
| `POST /admin/users/{id}/approve.json` | admin | `200` |
| `POST /admin/users/{id}/deny.json` | admin | `200` |
| `POST /admin/users/{id}/recovery-link.json` | admin | `201` |
| `POST /admin/users/{id}/send-recovery-email.json` | admin | `201` |
| `GET /health.json` | public | `200` |
| `GET /ready.json` | public | `200` / `503` |

> **About `201`:** the framework answers `201 Created` by default for every `POST` that does
> not override it, so many "read-like" POSTs (status, search, poll…) answer `201`, not `200`.
> The table above shows the real codes. The frontend only checks for success (2xx), so `200`
> and `201` are interchangeable for it. Matching them exactly is still the safest choice.

Paths:

- Every path ends in `.json`. The reverse proxy (Tent) routes `*.json` to the backend, and the
  frontend calls root-relative paths (`/auth/login.json`), with no `/api` or `/v1` prefix.
- Many reads are `POST` on purpose. Request bodies never go in query strings, and the
  proxy's cache key is the query string only (see [Cache headers](#25-cache-headers)).

#### 7. Operational endpoints

Public. They also send the no-cache headers.

##### 7.1 `GET /health.json`

Liveness. Always `200`, `{ "status": "ok" }`. It touches nothing.

##### 7.2 `GET /ready.json`

Readiness. Runs `SELECT 1` against the database.

- DB OK → `200`, `{ "status": "ok", "checks": { "database": "up" } }`
- DB failing → `503`, `{ "status": "error", "checks": { "database": "down" } }` (the reason is logged)
