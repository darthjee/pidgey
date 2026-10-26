# Issue: Frontend: SMTP connections screen and SmtpConnectionsClient

## Description

Add the admin UI for an organization's SMTP connections: a `SmtpConnectionsClient` covering every
`/smtp-connections*.json` endpoint, list / create / edit screens, the per-connection actions
(activate, send a test email, delete) and a **SMTP connections** item in the header's
**Organization** dropdown. Found while checking frontend/API compatibility in #32 (epic #24).

## Problem

The API contract defines the SMTP connection endpoints
(`docs/agents/specs/api/endpoints/pidgey-admin/smtp-connections.md`), but the frontend has no
client or screen for them. Without a connection that is created **and** activated, an
organization cannot send email (`POST /v1/messages` answers `409 NO_SMTP_CONNECTION`), so today
this can only be set up by calling the API by hand.

#42 (merged) added `ApiClient.getJson`, the `OrganizationClient` pattern and the header's
**Organization** dropdown; this issue builds on them.

## Expected Behavior

### `SmtpConnectionsClient`

New client in `frontend/assets/js/client/`, following `OrganizationClient`; a `401` is handled
by `ApiClient` as everywhere else (methods resolve to `undefined` when the session ended).

| Method | Call | Success |
| --- | --- | --- |
| `list()` | `GET /smtp-connections.json` | `200 { smtpConnections: [...] }` |
| `create(attributes)` | `POST /smtp-connections.json` | `201 { smtpConnection }` |
| `get(id)` | `GET /smtp-connections/{id}.json` | `200 { smtpConnection }` |
| `update(id, attributes)` | `PATCH /smtp-connections/{id}.json` | `200 { smtpConnection }` |
| `remove(id)` | `DELETE /smtp-connections/{id}.json` | `204` |
| `activate(id)` | `POST /smtp-connections/{id}/activate.json` (no body) | `200 { smtpConnection }` |
| `test(id, { to } = {})` | `POST /smtp-connections/{id}/test.json` | `201 { status, smtp }` |

- Only the fields the API accepts are sent (`name`, `host`, `port`, `encryption`, `username`,
  `password`, `fromAddress`; `to` for the test). `active` is never sent.
- `test` sends `to` only when given; with no `to`, the API uses the caller's own email.

### Routes and header

- `#/smtp-connections` — the list; `#/smtp-connections/new` — the create form;
  `#/smtp-connections/:id/edit` — the edit form (loads the connection with `get(id)`). The more
  specific routes are registered before `#/smtp-connections`.
- The header's **Organization** dropdown gets a **SMTP connections** item →
  `#/smtp-connections`, after **Settings**.
- Every logged-in, approved user has access (no admin check), as for the Organization screen.

### List screen

- Loads with `list()`, with a loading state and an error message on failure; an empty list
  shows an empty-state message. A `401` goes through the usual refresh / login-modal flow.
- Each row shows name, `host:port`, encryption, `fromAddress`, whether credentials are set
  (`username` / `hasPassword`) and an **Active** badge on the active connection.
- When no connection is active, a notice says that sending is disabled until one is activated.
- A **New connection** button goes to `#/smtp-connections/new`; each row links to its edit form.
- Per-row actions:
  - **Activate** — hidden on the active connection; on success the list shows the new active
    connection (only one active).
  - **Send test email** — opens a modal with an optional **Recipient** field (empty → the
    caller's own email, stated in the modal). On `201` it shows success with the SMTP reply
    (`smtp.code` / `smtp.message`). On a `SMTP_*` failure (`502`/`503`/`504`) it shows the
    error message and, when present, `error.smtp`. A `400 VALIDATION_FAILED` shows the details on
    the recipient field. The button is disabled while the request runs (it is synchronous and can
    be slow).
  - **Delete** — opens a confirmation modal; for the active connection the modal warns that
    sending stops (`POST /v1/messages` answers `409 NO_SMTP_CONNECTION`) until another one is
    activated. On `204` the row is removed.

### Create / edit form

- Fields: **Name**, **Host**, **Port**, **Encryption** (select: `none`, `tls`, `ssl`),
  **Username**, **Password**, **From address**.
- Client-side checks before calling the API (the API stays the source of truth): required
  `name`, `host`, `port`, `encryption`, `fromAddress`; `port` an integer between 1 and
  65535, sent as a JSON number; string fields at
  most 255 characters after trimming; `password` is never trimmed.
- **Password is write-only:**
  - the API never returns it; the edit form shows "Password: set" / "not set" from
    `hasPassword` and an empty password input;
  - on edit, `password` is sent only when the user types one (rotation); leaving it empty keeps
    the stored one;
  - a **Remove credentials** control on edit sends `username: ""` and `password: ""` (clearing
    both); clearing only one is left to the API's pairing rule.
- On create, `username` / `password` are sent only when filled; the username/password pairing
  rule ("both set or both empty") is checked client-side too.
- A `400 VALIDATION_FAILED` maps each detail to its field (`name`, `host`, `port`, `encryption`,
  `username`, `password`, `fromAddress`); the pairing message goes on the password field; any
  other error or unmatched detail is shown as a submit error.
- On success: create → back to `#/smtp-connections`; edit → a confirmation, with the form
  refilled from the returned item (password input cleared).
- An edit of an unknown or foreign id (`404 NOT_FOUND`) shows "SMTP connection not found" with a
  link back to the list.
- A new connection is always created inactive; the create form says so (activate from the list).

## Solution

### Changes

- `frontend/assets/js/client/SmtpConnectionsClient.js` (+ spec).
- `frontend/assets/js/components/resources/smtpConnections/pages/`: `SmtpConnections.jsx`
  (list), `SmtpConnectionNew.jsx` / `SmtpConnectionEdit.jsx` (or one form page with a mode), with
  `controllers/` and `helpers/` following the Organization / AdminUsers pattern; the test-email
  and delete modals as small components (like `DenyUserButton`).
- `utils/routing/HashRouteResolver.js` (`ROUTES`), `components/helpers/AppHelper.jsx`
  (`PAGES`), `components/common/header/helpers/HeaderHelper.jsx`
  (`renderOrganizationDropdown`).
- Optional shared validators in `utils/validation/fieldValidators.js` (port range, max length).
- Frontend specs: `docs/agents/specs/frontend/api-compatibility.md` (mark the M6 SMTP rows
  implemented), `layout.md`, `screens.md` (move `#/smtp-connections` out of "screens to come",
  describe the screens and the dropdown item).

### Testing

- Jasmine specs for `SmtpConnectionsClient` (paths, methods, bodies: no `active`, `to` only when
  given, `activate` without body) and for every controller / helper: loading and error states,
  password handling (sent only when typed; remove credentials sends `""` for both), port and
  pairing validation, `VALIDATION_FAILED` mapping, test-email success and `SMTP_*` failure with
  `error.smtp`, delete confirmation and the active-connection warning, routes and the dropdown
  item.
- CI `frontend_checks` (`npm run lint`, `npm run coverage`) passes.

### Out of scope

- The Vite dev proxy for `*.json` paths (#46) — needed to try the screen under `frontend_dev`,
  not for the specs.
- API tokens screen (#44); any API change; a deactivate action (the API has none in v1).
- Showing `createdAt` / `updatedAt`.

## Benefits

- Organizations can configure, test and activate their SMTP connection from the UI, which is the
  prerequisite for sending any email.
- The test email gives immediate feedback on wrong host, TLS or credentials before activation.
- Completes M6 of the frontend/API compatibility check for SMTP connections.
