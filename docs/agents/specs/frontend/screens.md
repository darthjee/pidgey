# Authentication, Routing and Screens

Purpose: define how the admin UI authenticates, routes and which screens it has.

The UI was copied from Citadel and speaks the Citadel auth contract
([api/authentication.md](../api/authentication.md)). Every endpoint it calls, and whether the
call matches the spec, is listed in [api-compatibility.md](api-compatibility.md).

## Authentication

### Tokens

- **Access token:** the JWT `access_token` cookie, `HttpOnly`, set and cleared by the API
  ([api/authentication/access-token.md](../api/authentication/access-token.md#cookie)). The UI
  never reads it; every request is sent with `credentials: 'same-origin'`, so the browser
  attaches it.
- **Refresh token:** returned in the body of login, register, refresh and the winning device
  poll, kept in `localStorage` under the key `pidgey_refresh_token`, and sent in request
  bodies (`refreshToken`) to refresh, status and logoff
  ([api/authentication/sessions.md](../api/authentication/sessions.md#refresh-token)). Logoff
  clears it even when the request fails.

### On load

`POST /auth/status.json` runs only when a refresh token is stored; with no stored token the UI
shows the logged-out state without a request. The outcomes:

- `loggedIn: true`: logged in, with the `isAdmin` flag (drives the admin link in the header);
- `status: "pending"`: the token is kept, the UI shows "awaiting approval", and the status is
  re-checked on window focus;
- anything else (unknown, revoked or expired token, or a `denied` user): the token is cleared
  and the UI shows the logged-out state.

The same check runs again after login and register, so a new `pending` user lands on
"awaiting approval".

### On `401` and `403`

- **`401`:** refresh once (`POST /auth/refresh.json` with the stored token), then retry the
  original request once. If there is no stored token, the refresh fails, or the retry gets a
  `401` again, the session ends: the token is cleared and the login modal opens in password
  mode. There is never a second refresh for the same request.
- **`403`** on admin screens (a non-admin session): redirect home.
- The UI never relies on `401` for business rules; the API answers `400`, `403`, `409`, `422`
  or `423` for those, and the UI shows the error body's `error.message` (see
  [api/conventions/errors.md](../api/conventions/errors.md)).

### Refresh single flight

Target behaviour, follow-up #41 (today every `401` starts its own refresh, see
[api-compatibility.md](api-compatibility.md#cross-cutting-behaviour)).

Why: the API rotates the refresh token on every refresh and treats a re-presented rotated
token as a replay, which **revokes every session of the user**
([api/authentication/sessions.md](../api/authentication/sessions.md#replay-detection)).
Parallel `401`s that each refresh with the same stored token trigger exactly that.

Rules:

- **Within a tab:** one in-flight refresh promise is shared by every request that got a `401`;
  each of them retries once after it settles.
- **Across tabs:** the refresh runs under a Web Locks API lock (`navigator.locks`). After
  taking the lock, the client re-reads the stored refresh token; when it differs from the one
  that got the `401`, another tab already rotated it, so the client skips the refresh and
  retries with the current cookie.
- **Without `navigator.locks`:** only the in-tab single flight applies.
- A failed shared refresh ends the session for every request waiting on it.

### Refresh token storage

The refresh token sits in `localStorage` and is readable by any script running in the admin
UI. This is kept for v1 (Citadel parity) as a **known limitation**. The frontend must keep
these mitigations:

- render through React escaping only;
- no `dangerouslySetInnerHTML`;
- no third-party scripts loaded at runtime.

Alternatives (an `HttpOnly` refresh cookie, a strict Content-Security-Policy) are listed under
[Pending decisions](#pending-decisions).

## Routing

Hand-written hash router (`#/...`, `frontend/assets/js/utils/routing/HashRouteResolver.js`),
so the web server only needs to serve `index.html`.

| Route | Screen |
| --- | --- |
| `#/` | Home. |
| `#/recover-password?token=` | Reset password: opens the login modal in reset-password mode with the token, then goes back to `#/`. |
| `#/admin/users` | Admin users: search, filter by status, approve, deny, recovery link and recovery email (admins only). |
| `#/admin/users/:id/edit` | Admin user edit: username, email, new password (admins only). |
| `#/account/my-account` | My account: edit username, email or password, confirmed with the current password. |
| `#/account/authorization-requests` | Authorization requests: list the caller's open device-login requests, authorize (with the password) or deny. |
| `#/organization` | Organization: show and rename the caller's organization (every logged-in, approved user; see [Organization screen](#organization-screen)). |

- Unknown routes fall back to home.
- The header shows links according to the auth state:
  - logged out: Login, Register and Recover (opening the modal);
  - `pending`: none (the "awaiting approval" view owns logout);
  - logged in and approved, in this order: the **Admin Users** link (admins only), the
    **Organization** dropdown, the **My account** dropdown (authorizations, account) and
    Logout.
- The **Organization** dropdown is shown to every logged-in, approved user, not only admins.
  Today it has one item, **Settings** → `#/organization`. The SMTP connections (#43) and API
  tokens (#44) screens add their items to this same dropdown.

### Organization screen

Uses `OrganizationClient` (`GET` and `PATCH /organization.json`,
[api/endpoints/pidgey-admin/organization.md](../api/endpoints/pidgey-admin/organization.md)).

- **Load:** fetches the organization and fills the **Organization name** field, with a loading
  state while the call runs and an error message when it fails. A `401` goes through the usual
  refresh / login-modal flow (see [On `401` and `403`](#on-401-and-403)); when the session
  ended, the screen stays idle.
- **Save:** the name, after trimming, must be 1–255 characters; otherwise an inline error is
  shown and no call is made. On success a confirmation is shown and the stored (trimmed) name
  returned by the API is put back into the field. A `400 VALIDATION_FAILED` shows its details
  on the name field; any other error is shown as a submit error.
- `createdAt` and `updatedAt` are not shown.

## Login modal

Login, registration and recovery are modal flows over the current screen, not routes. The
modal has these modes:

- **Password login:** username and password.
- **Register:** username, email, password and confirmation. Registration creates the
  organization together with the first user, so the form needs an **"Organization name"**
  field sent as `organizationName` (target, #40; without it every registration answers
  `400 VALIDATION_FAILED`).
- **Recover password:** email; the answer is always "sent".
- **Reset password:** new password and confirmation, with the token from the recovery link.
- **Device login:** username; creates an authorization request, shows a countdown to its
  expiry and polls until it resolves (`approved` logs in; `denied`, `expired` and `logged` end
  the flow).

## Pidgey screens to come

Targets that come with the follow-ups; none exists yet (see
[api-compatibility.md](api-compatibility.md#pidgey-admin)). Every user of an organization has
the same access to them, as for the [Organization screen](#organization-screen), and they are
reached from the header's **Organization** dropdown.

| Route | Screen |
| --- | --- |
| `#/smtp-connections` | SMTP connections: list, create and edit (the password is write-only, shown only as "set"/"not set"), activate, send a test email, delete (#43). |
| `#/api-tokens` | API tokens: list including revoked tokens, create with a name and scopes, show the plain token **once** and never store it, revoke (#44). |

## Pending decisions

- **Content-Security-Policy:** a strict CSP on `/admin/` (infra) to reduce the XSS exposure of
  the stored refresh token.
- **`HttpOnly` refresh cookie** instead of `localStorage`; tracked in
  [api/authentication/sessions.md](../api/authentication/sessions.md#pending-decisions).
