# API compatibility matrix
Create `docs/agents/specs/frontend/api-compatibility.md`:
- **Title:** "Frontend / API Compatibility".
- **Purpose line:** "record which API endpoints the admin UI calls, through which client
  method, and whether the call matches the API spec".

## Content
- A short intro on how it was checked:
  - clients in `frontend/assets/js/client/`;
  - the specs in `docs/agents/specs/api/endpoints/**`;
  - the frontend only checks 2xx, but the exact code is recorded.
- One table per area: auth/session, account and recovery, device authorization, admin users,
  Pidgey admin, operational, sending.
- Columns: `Method & path` | `Client method` | `Spec` (relative link to the section) |
  `Success` | `Status`.
- Rows:
  - **Auth:**
    - register → `AccountsClient.register`: **mismatch M1**, `organizationName` not sent (#40)
    - login → `AccountsClient.login`
    - refresh → `ApiClient` refresh path and `AccountsClient.refresh`: ✅ for shape; **M2**,
      no single flight (#41)
    - logoff → `logout`
    - status → `status`
    - recover → `recover`
    - reset-password → `resetPassword`. Note that `password_confirmation` is dropped as an
      unknown field.
    - account → `updateAccount`
  - **Device authorization:** the five `/auth/authorization-requests*` endpoints →
    `createAuthorizationRequest`, `pollAuthorizationRequest`, `listAuthorizationRequests`,
    `authorizeAuthorizationRequest`, `denyAuthorizationRequest`
  - **Admin users:** search, edit, approve, deny, recovery-link and send-recovery-email →
    the `AdminClient` methods. Note: the organization fields are ignored by the UI (#45).
  - **Pidgey admin:** every endpoint in `api/endpoints/pidgey-admin/*`, with "none yet" and
    the follow-up: #42 organization, #43 SMTP connections including activate/test, #44 API
    tokens.
  - **Operational** (`/health.json`, `/ready.json`) and **sending** (`POST /v1/messages`):
    "not called by the frontend".
- A **Cross-cutting behaviour** section, each item with ✅ or its follow-up:
  - `401` → refresh once and retry once (✅), plus single flight (M2, #41)
  - `403` on admin screens → redirect home (✅)
  - error body parsing (`error.code`, `error.message`, `error.details`) (✅)
  - `204` / empty body handling (✅)
  - camelCase fields (✅)
  - refresh token in `localStorage` (see screens.md, known limitation)
  - dev proxy for `*.json` paths (#46)
- **Pending decisions:** none, or anything found while building the matrix.

Verify every row against the actual client code before marking it ✅.

## Files to Change
- `docs/agents/specs/frontend/api-compatibility.md` — new file.
- `docs/agents/specs/frontend.md` — add the row for `api-compatibility.md` to the index table.
