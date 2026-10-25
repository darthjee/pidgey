# Issue: API specs: conventions (Citadel contract)

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

`docs/agents/specs/api/conventions.md` describes a contract the admin frontend does not use:
`snake_case` fields, an error body with `details` keyed by field, `422` for validation, and
no rules for paths, the origin/CSRF check or guard order. `email/failures.md` uses lower-case
error codes and puts the SMTP reply in `details`. `architecture/backend.md` reserves `/admin/`
for the UI, which collides with the Citadel `/admin/users/*.json` endpoints, and
`frontend/development.md` only proxies `/v1` in development. Every other endpoint spec of the
epic depends on these shared rules, so they must be settled first.

## Expected Behavior

Rewrite `docs/agents/specs/api/conventions.md` (splitting it if it grows too big) to define the
shared rules for every endpoint, following the Citadel conventions:

- JSON content type, `camelCase` field names, ISO 8601 UTC dates with milliseconds, bodies on
  `DELETE`.
- Paths: root-relative `*.json`, many reads as `POST`; note that the public sending API keeps `/v1`.
- Validation: before business logic, unknown fields dropped, all errors at once, message
  templates, guards before validation.
- Error body (`error.code`, `error.message`, `error.details`, `statusCode`, `timestamp`),
  category codes and specific codes, `500 INTERNAL_ERROR`, body-parser and unknown-route errors.
- The "never `401` for a business rule" rule.
- Origin/CSRF check and its relation with CORS (`ALLOWED_ORIGINS`, `FRONTEND_BASE_URL`).
- ~~Cache headers~~: not carried over (see the decision below).
- Success codes (`201` on most POSTs) and guard order.
- Decide (or mark pending) whether `POST /v1/messages` adopts the same error body.
- Update `docs/agents/specs/api/pending.md` (CORS item) and `docs/agents/specs/email/failures.md`
  where they conflict.

### Depends on

Nothing; can start right away (in parallel with "Users & organizations data model").

## Solution

### Decision: one error contract, including `POST /v1/messages`

The public sending API uses the **same error envelope** as every other endpoint (the
Citadel shape); there is a single error formatter in the backend.

- Codes are upper-case. The email failure codes in `email/failures.md` are renamed:
  `VALIDATION_FAILED`, `NO_SMTP_CONNECTION`, `SMTP_CONNECTION_FAILED`, `SMTP_TLS_FAILED`,
  `SMTP_AUTH_FAILED`, `SMTP_REJECTED`, `SMTP_TEMPORARY_FAILURE`, `SMTP_TIMEOUT`,
  `INTERNAL_ERROR`.
- Validation errors answer `400 VALIDATION_FAILED` with `details` as an array of strings
  (Citadel message templates), replacing `422 validation_error`.
- SMTP failures carry the server reply in a dedicated `error.smtp` object
  (`{ "code": 550, "message": "..." }`), matching the `smtp` field of the success body.
  `details` stays reserved for validation messages. Credentials are never included.
- The HTTP status mapping for send failures is kept (`409` no active connection, `502`
  connection/TLS/auth/rejected, `503` temporary failure, `504` timeout, `500` internal).
- Every error body includes `statusCode` and `timestamp`.

Example:

```json
{
  "error": {
    "code": "SMTP_REJECTED",
    "message": "Recipient rejected",
    "smtp": { "code": 550, "message": "5.1.1 User unknown" }
  },
  "statusCode": 502,
  "timestamp": "2026-10-25T12:00:00.000Z"
}
```

Files: `api/conventions.md` (envelope, the `error.smtp` extension) and
`email/failures.md` (codes and statuses).

### Decision: no cache headers for now

Pidgey has no Tent or reverse-proxy cache layer (`docker/images.md`), and nothing in the API
is meant to be cached. The specs therefore **do not** require `X-Skip-Cache` or
`Cache-Control` headers: the Citadel "Cache headers" section (2.5) and porting note 9 are not
carried over. Record it as a **Pending decision** in `api/pending.md`: revisit if a caching
proxy is introduced, or if `Cache-Control: no-store` is wanted on credential-bearing
responses (login, refresh, token creation).

### Decision: routing — `*.json` is always the API

- **Path rule:** every API path ends in `.json` and is root-relative, with no prefix
  (`/auth/login.json`, `/admin/users/search.json`, `/health.json`). Only the public sending
  API keeps the `/v1` prefix (`POST /v1/messages`).
- **`*.json` always goes to the backend**, anywhere in the path tree, including under
  `/admin/`. The UI never ships `.json` files under `/admin/`.
- Production needs no change: Apache already rewrites any request that is not an existing
  file to the front controller, so `/admin/users/*.json` falls through to the router.
- Spec updates in this issue (specs only):
  - `api/conventions.md`: the path rule above.
  - `architecture/backend.md`: relax "`/admin/` is reserved for the UI and the API router
    never claims it" to "non-`.json` paths under `/admin/` are reserved for the UI; `*.json`
    paths are always routed to the API".
  - `frontend/development.md` ("API access"): Vite proxies `/v1` **and** every `*.json` path
    (regex key such as `'^/.*\.json$'`, which also wins over Vite's own `/admin/` base) to
    `http://app:80` with `changeOrigin: false`. Replace the PHP-session wording with the
    `access_token` cookie (same-origin requirement unchanged; the cookie must not pin a
    `Domain` or port).
- The actual `frontend/vite.config.js` change belongs to a later implementation issue.

### Decision: split `api/conventions.md`

`api/conventions.md` becomes a short index of `api/conventions/`, following the granularity
rule in `docs/agents/specs.md`. Suggested files (adjust if the content reads better
otherwise):

| File | Content |
| --- | --- |
| `format.md` | JSON content type, `camelCase`, dates, bodies on `DELETE`, the path rule, success codes. |
| `validation.md` | Validation order, unknown fields dropped, message templates, guards before validation. |
| `errors.md` | Error envelope, category and specific codes, `error.smtp`, status codes, "never `401` for a business rule". |
| `origin-check.md` | Origin/CSRF check, trusted origins and CORS. |
| `guard-order.md` (or folded into `format.md`) | Origin check → gate → admin check → validation → handler. |

Update `docs/agents/specs/api.md` to link the new index.

### Reference: Citadel contract (verbatim excerpts)

Excerpts from "Citadel API — endpoint contract for a reimplementation". Links point to the Citadel source tree and are kept for traceability only.

### 1. Overview

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

### 2. Conventions

#### 2.1 Content type

- Requests and responses are JSON. The frontend sends `Content-Type: application/json` and
  `Accept: application/json` on every call, with `credentials: same-origin`, so the cookie
  goes along.
- Field names are **camelCase** (`refreshToken`, `isAdmin`, `newPassword`, `requestIp`…).
- Dates are ISO 8601 strings in UTC with milliseconds, e.g. `"2026-10-17T12:00:00.000Z"`.
- `DELETE /auth/logoff.json` carries a JSON **body**. Your HTTP stack must read bodies on
  `DELETE`.

#### 2.2 Validation

- Validation runs on the request body **before** any business logic.
- **Unknown fields are silently dropped**, not rejected. For example, the frontend sends
  `password_confirmation` on reset-password, and the backend ignores it.
- Every field error is collected and reported at once (see the error body below).
- Validation messages follow these templates (`<field>` is the property name):

| Rule | Message |
| --- | --- |
| must be a string | `<field> must be a string` |
| not empty | `<field> should not be empty` |
| email | `<field> must be an email` |
| min length N | `<field> must be longer than or equal to N characters` |
| max length N | `<field> must be shorter than or equal to N characters` |
| one of a set | `<field> must be one of the following values: a, b, c` |

- Order: guards run **before** validation. An anonymous request to a protected route with an
  invalid body therefore gets `401`, not `400`.

#### 2.3 Error body

Every error, whatever produced it, has the same shape
([`core/http-exception.filter.ts`](/Users/darthjee/projetos/orca/citadel/backend/src/core/http-exception.filter.ts)):

```json
{
  "error": {
    "code": "UNAUTHORIZED",
    "message": "Invalid or expired access token"
  },
  "statusCode": 401,
  "timestamp": "2026-10-25T12:00:00.000Z"
}
```

Validation errors also carry `details`, one entry per failed rule, and `message` is those
entries joined by `"; "`:

```json
{
  "error": {
    "code": "VALIDATION_FAILED",
    "message": "email must be an email; password must be longer than or equal to 8 characters",
    "details": ["email must be an email", "password must be longer than or equal to 8 characters"]
  },
  "statusCode": 400,
  "timestamp": "2026-10-25T12:00:00.000Z"
}
```

Rules:

- `error.code` is a **specific code** when the endpoint sets one (table below), otherwise the
  **category code** derived from the status.
- Unexpected errors (bugs, DB down…) answer `500` with
  `{ "code": "INTERNAL_ERROR", "message": "Internal server error" }`. The real error is logged,
  never sent.
- Body-parser failures (malformed JSON, body too large, bad charset) answer their own 4xx
  status (`400`, `413`, `415`) with the category code and the standard reason phrase, e.g.
  `{ "code": "BAD_REQUEST", "message": "Bad Request" }`.
- An unknown route answers `404` with code `NOT_FOUND` (message like `Cannot POST /foo.json`).

Category codes, by status
([`core/error-codes.ts`](/Users/darthjee/projetos/orca/citadel/backend/src/core/error-codes.ts)):

| Status | Code |
| --- | --- |
| 400 | `BAD_REQUEST` (or `VALIDATION_FAILED` for body validation) |
| 401 | `UNAUTHORIZED` |
| 403 | `FORBIDDEN` |
| 404 | `NOT_FOUND` |
| 409 | `CONFLICT` |
| 423 | `LOCKED` |
| 429 | `TOO_MANY_REQUESTS` (reserved, not currently emitted) |
| 500 | `INTERNAL_ERROR` |
| other | `HTTP_<status>`, e.g. `HTTP_422` |

Specific codes:

| Code | Status | Message | Where |
| --- | --- | --- | --- |
| `USERNAME_TAKEN` | 409 | `username is not available` (register) / `Username already in use` (account & admin edit) | register, account, admin edit |
| `EMAIL_TAKEN` | 409 | `email is not available` (register) / `Email already in use` (account & admin edit) | register, account, admin edit |
| `USER_DENIED` | 403 | `This account has been denied access` | login |
| `USER_NOT_PENDING` | 422 | `Only pending users can be approved` | admin approve |
| `CANNOT_DENY_SELF` | 422 | `You cannot deny your own account` | admin deny |

> The frontend's API client reacts to **`401` only** by calling `/auth/refresh.json` and
> retrying once. That is why every business rejection in this API is deliberately `400`, `403`
> or `422`, **never `401`**. A `401` must mean "your credentials are invalid or expired" and
> nothing else.

#### 2.4 CSRF / origin check

Every `POST`, `PUT`, `PATCH` and `DELETE` goes through an origin check, **including the public
routes**. It runs before authentication
([`core/origin.guard.ts`](/Users/darthjee/projetos/orca/citadel/backend/src/core/origin.guard.ts)):

1. `GET` / `HEAD` / `OPTIONS` → always allowed.
2. If `Sec-Fetch-Site` is present: allowed when it is `same-origin` or `none`, or when the
   `Origin` header is in the trusted-origins list. Otherwise rejected.
3. If `Sec-Fetch-Site` is absent and `Origin` is absent → allowed (non-browser clients).
4. If `Sec-Fetch-Site` is absent and `Origin` is present → allowed when `Origin` is trusted or
   its host equals the `Host` header. Otherwise rejected.

Rejection: `403`, `{ "code": "FORBIDDEN", "message": "Cross-site request rejected" }`.

The trusted-origins list is the same list that drives CORS: `ALLOWED_ORIGINS`
(comma-separated bare origins), falling back to the origin of `FRONTEND_BASE_URL`. When neither
is set, CORS is off (same-origin only) and the trusted list is empty. CORS, when on, is
credentialed (`Access-Control-Allow-Credentials: true`).

#### 2.5 Cache headers

The proxy caches any 2xx response to a `*.json` URL, whatever the method, keyed on the query
string only, **unless** the response carries `X-Skip-Cache: true`. Every endpoint in this API
is user-specific, credential-bearing or operational, so **every endpoint** sends:

```
X-Skip-Cache: true
Cache-Control: no-store
```

This covers health/ready as well. Error responses are 4xx/5xx, so the proxy never caches them,
but sending the headers on errors does no harm.

### 10. Notes for a port

Points that are easy to get wrong:

1. **Never answer `401` for a business rule.** The frontend refreshes and retries on `401`.
   Use `400` / `403` / `422` / `423` exactly as listed.
2. **Enumeration safety.** Login compares against a dummy bcrypt digest when the user is
   missing. Recover always answers `{ sent: true }`. Device-request create looks the same for
   unknown usernames and over-limit callers. A poll with an unknown uuid looks the same as one
   with a wrong token (`404`). Authorize always runs the password compare, even when locked out.
3. **Hash every bearer secret at rest** with plain SHA-256 hex (refresh tokens, reset tokens,
   poll tokens) and look them up by hash. Passwords use bcrypt cost 10. Another adaptive hash
   (for example Argon2) works for new installs. To share an existing Citadel database, keep
   bcrypt (`$2a$`/`$2b$` digests).
4. **Atomic single-winner transitions.** Use conditional `UPDATE … WHERE <state> …` and check
   the affected-row count for refresh rotation, the device claim, every device state change and
   admin approve.
5. **The gate reads the DB on every protected request** (session row active + user approved).
   Don't trust the JWT's `username` or `isAdmin`.
6. **Logout ends the session, not just the token.** Revoke every active row with the presented
   token's `session_uuid`, even if that token was already rotated.
7. **POST success codes are mostly `201`** (see the table in [§1](#1-overview)). Only
   recover, reset-password, approve and deny answer `200`, account answers `200` (`PATCH`), and
   logoff answers `204`.
8. **Unknown body fields are dropped, not rejected.** Field names are camelCase.
9. **Every response sends `X-Skip-Cache: true` + `Cache-Control: no-store`.** Without them the
   proxy can serve one user's login response to another user.
10. **The cookie is `Secure`**, so local development needs HTTPS (or a proxy that terminates
    it), otherwise browsers drop the cookie.

## Benefits

- One shared contract (format, validation, errors, origin check, routing) that every endpoint
  spec of the epic can reference instead of repeating.
- Specs that match the copied Citadel frontend, so the backend can be built against them
  without frontend changes.
- A single error envelope for both the admin API and the public sending API, so the backend
  needs only one error formatter.
