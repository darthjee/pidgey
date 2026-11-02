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
| `#/admin/users` | Admin users: list with each user's organization, search, filter by status, approve, deny, recovery link and recovery email (admins only). |
| `#/admin/users/:id/edit` | Admin user edit: username, email, new password (admins only). |
| `#/account/my-account` | My account: edit username, email or password, confirmed with the current password. |
| `#/account/authorization-requests` | Authorization requests: list the caller's open device-login requests, authorize (with the password) or deny. |
| `#/organization` | Organization: show and rename the caller's organization (every logged-in, approved user; see [Organization screen](#organization-screen)). |
| `#/smtp-connections` | SMTP connections: list the organization's connections, activate, send a test email and delete (every logged-in, approved user; see [SMTP connections screens](#smtp-connections-screens)). |
| `#/smtp-connections/new` | New SMTP connection: create form (see [SMTP connection form](#smtp-connection-form)). |
| `#/smtp-connections/:id/edit` | Edit SMTP connection: edit form; the password is write-only (see [SMTP connection form](#smtp-connection-form)). |
| `#/api-tokens` | API tokens: list the organization's tokens, including revoked ones, and revoke (every logged-in, approved user; see [API tokens screens](#api-tokens-screens)). |
| `#/api-tokens/new` | New API token: create form, then the plain token shown **once** (see [API token create](#api-token-create)). |

- Unknown routes fall back to home.
- The header shows links according to the auth state:
  - logged out: Login, Register and Recover (opening the modal);
  - `pending`: none (the "awaiting approval" view owns logout);
  - logged in and approved, in this order: the **Admin Users** link (admins only), the
    **Organization** dropdown, the **My account** dropdown (authorizations, account) and
    Logout.
- The **Organization** dropdown is shown to every logged-in, approved user, not only admins.
  It lists, in this order, **Settings** → `#/organization`, **SMTP connections** →
  `#/smtp-connections` and **API tokens** → `#/api-tokens`.

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

### SMTP connections screens

Use `SmtpConnectionsClient` (the seven `/smtp-connections*.json` endpoints,
[api/endpoints/pidgey-admin/smtp-connections.md](../api/endpoints/pidgey-admin/smtp-connections.md)).
Every user of the organization has the same access; there is no admin check. In every load, a
`401` goes through the usual refresh / login-modal flow (see [On `401` and `403`](#on-401-and-403));
when the session ended, the screen stays idle.

#### SMTP connections list

- **Load:** lists the connections, with a loading state while the call runs and an error
  message when it fails. An empty list shows an empty-state message.
- **Rows:** name (link to `#/smtp-connections/{id}/edit`), `host:port`, encryption,
  `fromAddress`, credentials ("set" when a username is stored or `hasPassword` is true,
  otherwise "none"), and an **Active** badge on the active connection. A **New connection**
  button links to `#/smtp-connections/new`.
- **No active connection notice:** shown when no connection is active (including an empty
  list): sending is disabled until one is activated.
- **Row actions:**
  - **Activate** (hidden on the active row): activates the connection; on success the returned
    item becomes the only active row. Errors are shown as a list error.
  - **Delete:** asks for confirmation first. For the active connection, the message warns that
    sending stops (`POST /v1/messages` answers `409 NO_SMTP_CONNECTION`) until another
    connection is activated. On success the row is removed; errors are shown as a list error.
  - **Send test email:** opens the test-email modal for that row.

#### Test-email modal

- One optional **Recipient** field; when it is left empty, the email goes to the caller's own
  email. A blank value is not sent (`to` is omitted).
- The test is synchronous and can take up to the send-flow timeouts: while it runs, the submit
  button is disabled and a "sending…" state is shown.
- Results:
  - success (`201`): a success message with the SMTP server reply (`smtp.code`,
    `smtp.message`);
  - an `SMTP_*` failure (`502` / `503` / `504`): the error message, plus the SMTP server reply
    (`error.smtp` code and message) when present;
  - `400 VALIDATION_FAILED`: its details on the recipient field;
  - any other error: a generic error in the modal.
- Closing the modal resets its state.

#### SMTP connection form

One form serves `#/smtp-connections/new` and `#/smtp-connections/:id/edit`, with the fields
**Name**, **Host**, **Port**, **Encryption** (`none`, `tls`, `ssl`), **Username**, **Password**
and **From address**.

- **Edit load:** fills every field except the password and shows "Password: set" / "not set"
  from `hasPassword`. A `404` shows "SMTP connection not found" with a link back to the list;
  any other failure shows a load error.
- **Write-only password:** the password is never shown, never put back into the form and is
  cleared from the input after a successful save. It is sent only when typed.
- **Client-side rules** (the API stays authoritative; a failing rule shows an inline error and
  no call is made):
  - `name`, `host`, `encryption` and `fromAddress` are required after trimming; `port` is
    required;
  - `port` is an integer from 1 to 65535, sent as a JSON number;
  - `name`, `host`, `username` and `fromAddress` are at most 255 characters after trimming;
    `password` is at most 255 characters and is never trimmed;
  - `fromAddress` is a valid email;
  - username and password must be both set or both empty on the merged state: on create both
    are filled or both empty; on edit a typed username without a password is accepted only when
    a password is stored, and a typed password without a username only when a username is
    stored. The message is shown on the password field.
- **Payload:** on create, the trimmed fields and the numeric port, with `username` and
  `password` only when filled. On edit, every non-credential field, `username` when changed and
  `password` only when typed. **Remove credentials** (shown on edit when credentials are
  stored) sends `username: ""` and `password: ""`. `active` is never sent.
- **Errors:** `400 VALIDATION_FAILED` details are shown on the field they name; anything
  unmatched and any other error is shown as a submit error.
- **Success:** create goes back to `#/smtp-connections`; edit shows a confirmation, refills
  the fields from the returned item and refreshes "set" / "not set".
- The create form states that new connections start inactive and are activated from the list.

### API tokens screens

Use `ApiTokensClient` (the three `/api-tokens*.json` endpoints,
[api/endpoints/pidgey-admin/api-tokens.md](../api/endpoints/pidgey-admin/api-tokens.md)).
Every user of the organization has the same access; there is no admin check. A `401` goes
through the usual refresh / login-modal flow (see [On `401` and `403`](#on-401-and-403)); when
the session ended, the screen stays idle.

#### API tokens list

- **Load:** lists the tokens, with a loading state while the call runs and an error message
  when it fails. An empty list shows "No API tokens yet.". A **New token** button links to
  `#/api-tokens/new`.
- **Rows:** in the API order (id ascending): `name`, `tokenPrefix` (followed by "…"), `scopes`,
  `createdAt` and `lastUsedAt` ("Never" when `null`), dates in the user's locale.
- **Revoked tokens** (`revokedAt` set) stay in the list with a **Revoked** badge and their
  `revokedAt` date, and have no Revoke button.
- **Revoke** (active tokens only): asks for confirmation first (integrations using the token
  stop working immediately; there is no un-revoke). On success the list is reloaded, so
  `revokedAt` comes from the API. A `404` reloads the list and shows "This API token no longer
  exists."; any other error is shown as a list error.

#### API token create

- **Fields:** **Name** and one **Scopes** checkbox per known scope; only `send` exists today and
  it is checked by default.
- **Validation:** the name, after trimming, must be 1–255 characters and at least one scope
  must be checked; otherwise inline errors are shown and no call is made. The trimmed name is
  sent. A `400 VALIDATION_FAILED` shows its details on the name / scopes fields; any other
  error (or a detail matching no field) is shown as a submit error.
- **One-time token panel:** on success the form is replaced by a panel with a warning ("Copy
  this token now. It cannot be shown again."), the plain `token` in a read-only field, a
  **Copy** button and a **Done** link back to `#/api-tokens`. Copy uses
  `navigator.clipboard.writeText`; when the clipboard is missing (non-secure context) or the
  copy is refused, "Copy failed — select the token and copy it manually" is shown.
- **Never stored:** the plain token lives only in the create screen's component state. It is
  never written to `localStorage`, `sessionStorage`, the URL or a module-level variable, and
  never logged; it is gone once the user leaves the page (Done or any other navigation).

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

## Pending decisions

- **Content-Security-Policy:** a strict CSP on `index.html` at `/` (sent by the static-file
  handler) to reduce the XSS exposure of the stored refresh token. Only
  `frame-ancestors 'none'` is already decided
  ([request-handler/static-files.md](../request-handler/static-files.md#response-headers)); the
  rest of the policy is open.
- **`HttpOnly` refresh cookie** instead of `localStorage`; tracked in
  [api/authentication/sessions.md](../api/authentication/sessions.md#pending-decisions).
