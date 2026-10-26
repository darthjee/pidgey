# Issue: Frontend specs: compatibility check with the API specs

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

The frontend specs under `docs/agents/specs/frontend/` predate the Citadel contract. They
describe a PHP session, `GET /v1/session` and routes that do not exist. Nothing records
whether the copied frontend actually speaks the API contract written in #25–#31. The check
done while enhancing this issue found real mismatches, two of which break the UI:
registration fails, and parallel `401`s can revoke every session of the user.

### Original goal

Confirm the frontend (copied from Citadel) is compatible with the written specs, and align
the frontend specs:

- List every endpoint the frontend calls (`frontend/assets`, `frontend/specs`) with its
  request/response usage, and compare with `docs/agents/specs/api/**`: paths, methods,
  field names, status handling (`401` → refresh and retry), cookie/refresh-token storage.
- Update `docs/agents/specs/frontend/screens.md` (and other `frontend/*` specs) to the new
  auth model: no PHP session, `POST /auth/status.json` on load, refresh token storage,
  login/register/recover/reset screens, device authorization, account, admin users, and the
  Pidgey screens (organization, SMTP connections, tokens).
- Report mismatches: fix the specs when the specs are wrong, or open follow-up issues for
  frontend changes (no frontend code changes here).

### Findings

The clients in `frontend/assets/js/client/` and the routes in
`frontend/assets/js/utils/routing/HashRouteResolver.js` were compared with
`docs/agents/specs/api/**`.

**Compatible:**
- Paths, methods and camelCase fields of every auth, device-authorization and admin-user call.
- `401` → refresh and retry once.
- `status.json`'s `pending` handling.
- Device-authorization poll statuses (`open`, `approved`, `denied`, `expired`, `logged`).
- The admin search shape (`users`, `currentUserId`).
- `403` → redirect home.
- `password_confirmation` sent on register and reset-password, which the API drops as an unknown field.

**Mismatches:**

| # | Mismatch | Effect | Resolution |
| --- | --- | --- | --- |
| M1 | `AccountsClient.register` does not send `organizationName`, which `POST /auth/register.json` requires | every registration answers `400 VALIDATION_FAILED` | spec stays; frontend follow-up #40 |
| M2 | `ApiClient.handleUnauthorized` refreshes once per `401`, with no single flight in a tab or across tabs | parallel `401`s re-present a rotated token, the backend treats it as a replay and **revokes every session of the user** | spec stays (strict replay rule kept); frontend rule added; follow-up #41 |
| M3 | Refresh token kept in `localStorage` (`pidgey_refresh_token`) | readable by XSS (pending decision in `authentication/sessions.md`) | kept for v1 as a known limitation, with mitigations (see Decisions) |
| M4 | `frontend/screens.md` describes a PHP session, `GET /v1/session` and routes `#/login`, `#/register`, `#/organization`, `#/tokens` | spec fully stale | rewrite |
| M5 | `frontend/layout.md` lists target clients `SessionClient`, `OrganizationsClient`, … and forbids building on `/auth/*` | backwards: the Citadel clients are the contract | rewrite |
| M6 | No organization, SMTP connection, API token or test-email screens or clients; `ApiClient` has no `GET` method | Pidgey admin endpoints are unreachable from the UI | follow-ups #42, #43, #44 |
| — | The admin users list ignores the organization fields the API returns | cosmetic | optional follow-up #45 |

## Expected Behavior

- A new `docs/agents/specs/frontend/api-compatibility.md` maps every API endpoint to the
  client method that calls it (or "none yet" / "not called by the frontend"), its spec, and
  its status (✅, or a mismatch with its follow-up issue).
- `docs/agents/specs/frontend/screens.md` and `layout.md` describe the frontend as it is
  (Citadel auth model, real routes, modal flows, existing clients). They also describe the
  targets that come with the follow-ups (Pidgey screens, new clients, single-flight refresh).
- No spec under `docs/agents/specs/**` still refers to the PHP session or `GET /v1/session`.
- Frontend code changes are tracked in follow-ups #40–#45, not done here.

## Solution

### Decisions

1. **Deliverable:**
   - New `docs/agents/specs/frontend/api-compatibility.md`: a matrix with one row per endpoint.
     - Columns: method and path, client method (or "none yet"), spec link, status (✅ or mismatch with its follow-up issue).
     - Every endpoint in `api/endpoints/**` is covered, including the Pidgey admin endpoints and `POST /v1/messages`. `/v1/messages` is marked "not called by the frontend".
     - The operational endpoints are also marked "not called by the frontend".
   - Rewrite `docs/agents/specs/frontend/screens.md` (see 2).
   - Rewrite `docs/agents/specs/frontend/layout.md` (see 3).
   - Update `docs/agents/specs/frontend.md` (the index) with the new file.
2. **`screens.md` rewrite:**
   - **Auth model:**
     - JWT `access_token` cookie (HttpOnly, set by the API, sent with `credentials: 'same-origin'`).
     - The refresh token is stored in `localStorage` under `pidgey_refresh_token`.
     - On load, `POST /auth/status.json` runs only when a refresh token is stored. Its outcomes:
       - logged in, with the admin flag;
       - `pending`, which keeps the token and shows "awaiting approval";
       - anything else, which clears the token.
     - On a `401`: refresh once, then retry once. When that fails, clear the token and open the login modal.
     - `403` on admin screens redirects home.
     - The UI never relies on `401` for business rules; that is the API's job.
   - **Routes as they are:**

     | Route | Screen |
     | --- | --- |
     | `#/` | home |
     | `#/recover-password?token=` | reset password |
     | `#/admin/users` | admin users |
     | `#/admin/users/:id/edit` | admin user edit |
     | `#/account/my-account` | my account |
     | `#/account/authorization-requests` | authorization requests |

     Unknown routes fall back to home.
   - **Modal flows:** the login modal handles password login, register, recover password, and device login (create request, countdown, poll).
   - **Pidgey screens to come** (targets, linked to follow-ups):

     | Route | Screen |
     | --- | --- |
     | `#/organization` | organization (#42) |
     | `#/smtp-connections` | SMTP connections, with activate and test email (#43) |
     | `#/api-tokens` | API tokens, with the token shown once and never stored (#44) |

   - Register needs an "Organization name" field (#40).
   - Remove the stale pending decision "routing library vs. hand-written router" (settled: hand-written hash router). Remove the admin-screen pending item too (settled: admin users screens exist).
3. **`layout.md` rewrite:**
   - **Clients as they are:**
     - `ApiClient` (`postJson`, `patchJson`, `deleteJson`, refresh and retry on `401`, error body parsing)
     - `AccountsClient`
     - `AdminClient`
     - `AuthSession`, `AuthStatus`, `AuthEvents`, `LoginModalEvents`, `ApiError`, `pickDefined`
   - **Target additions:**
     - `ApiClient.getJson`
     - `OrganizationClient`
     - `SmtpConnectionsClient`
     - `ApiTokensClient`

     Each new client has one method per endpoint and composes `ApiClient`, the same as the existing clients.
   - Remove "must not build on `/auth/*`": the Citadel `/auth/*` and `/admin/users/*` routes are the contract.
4. **Refresh single flight (M2):**
   - The API keeps the strict replay rule.
   - `screens.md` (auth model) gains the rule:
     - Within a tab, one in-flight refresh promise is shared by every request that got a `401`.
     - Across tabs, the refresh runs under a Web Locks API lock (`navigator.locks`). After taking the lock, the client re-reads the stored refresh token and skips the refresh when another tab already rotated it.
     - Without `navigator.locks`, only the in-tab single flight applies.
   - `authentication/sessions.md`'s "checked in #32" note is updated to point to this rule and #41.
5. **Refresh-token storage (M3):**
   - Keep `localStorage` for v1 (Citadel parity) as a **known limitation**.
   - `screens.md` records the XSS mitigations the frontend must keep:
     - render through React escaping only;
     - no `dangerouslySetInnerHTML`;
     - no third-party scripts at runtime.
   - The `HttpOnly` refresh-cookie idea stays a **pending decision** in `authentication/sessions.md`, reworded to say #32 reviewed it and kept `localStorage` for v1.
   - New pending item: a strict Content-Security-Policy on `/admin/` (infra), added to the frontend specs' pending decisions.
6. **Follow-up issues** (frontend code, outside this specs-only issue; created during enhancement as comment-only cross-references):
   - #40: send `organizationName` on register
   - #41: single-flight refresh across requests and tabs
   - #42: organization screen, `OrganizationClient` and `ApiClient.getJson`
   - #43: SMTP connections screen and `SmtpConnectionsClient`
   - #44: API tokens screen and `ApiTokensClient`
   - #45 (optional): show the organization in the admin users list
7. **API specs:**
   - Changed only where they are found wrong; none were found wrong in this check.
   - The only API spec edit is the `sessions.md` wording in decisions 4 and 5.

### Scope

- **In scope:**
  - `docs/agents/specs/frontend/*.md`: `screens.md` and `layout.md` rewritten, new `api-compatibility.md`, and the `frontend.md` index.
  - The `sessions.md` wording in decisions 4 and 5.
  - Any other stale reference to the PHP session or `/v1/session` in `docs/agents/specs/**`.
- **Out of scope:**
  - Frontend code (the follow-ups #40–#45).
  - PHP code and migrations.
  - Docker and CSP wiring (pending).
  - Changes to the API contract.

### Edge cases

- **A stored refresh token for a `pending` user:** kept, and the UI shows "awaiting approval". Re-checked on window focus, as now.
- **A stored token for a `denied` user:** status answers the anonymous shape, and the token is cleared.
- **A `401` on the retried request:** the session ends, with no second refresh.
- **The refresh call itself returns `401` or another error:** the session ends.
- **A tab that waited on the lock:** if the stored token changed, it retries without refreshing.
- **`204` on logoff** and other empty or non-JSON bodies: parsed as `{}`. Already compatible.
- **Two endpoints answering `200` vs `201`:** the frontend only checks 2xx. The matrix still records the exact code.

### Testing

Specs only:
- Re-check every matrix row against the client files and the API spec it links.
- Check every relative link in the changed files.
- `grep` `docs/agents/specs/**` for `/v1/session`, "PHP session", `SessionClient` and `OrganizationsClient`: no hits remain, except where they are documented as removed.
- Every changed spec keeps the title / `Purpose:` / content / **Pending decisions** format.

### Known limitations

- The refresh token sits in `localStorage` and is readable by any XSS in the admin UI. Mitigations are listed in decision 5, and the `HttpOnly` cookie stays pending.
- Until #40 ships, registration from the UI fails with `400`.
- Until #41 ships, parallel `401`s can revoke every session of the user.
- The Pidgey admin endpoints are reachable only through the API until #42–#44 ship.

## Benefits

- The frontend specs match the code and the API contract, so follow-up frontend work
  (#40–#45) starts from correct docs.
- The two breaking mismatches (register and replay on parallel refresh) are recorded with
  owners instead of being found in production.
- The compatibility matrix gives one place to check when either side changes.

## Depends on

All endpoint sub-issues: "Auth endpoints", "Device-authorization endpoints",
"Admin user endpoints", "Pidgey admin & operational endpoints".

## Reference: Citadel contract (verbatim excerpts)

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
