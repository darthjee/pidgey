# Plan: Frontend: SMTP connections screen and SmtpConnectionsClient

Issue: [43-frontend-smtp-connections-screen-and-smtpconnectionsclient.md](../../issues/43-frontend-smtp-connections-screen-and-smtpconnectionsclient.md)

## Overview
Build the admin UI for an organization's SMTP connections on top of what #42 added
(`ApiClient.getJson`, the `OrganizationClient` / `Organization` page pattern and the header's
**Organization** dropdown): a client for the seven `/smtp-connections*.json` endpoints, three
routes (list, new, edit), per-row Activate / Send test email / Delete on the list, and a
**SMTP connections** item in the Organization dropdown.

## Context
- Contract: `docs/agents/specs/api/endpoints/pidgey-admin/smtp-connections.md` (item shape,
  field rules, credential pairing on the merged state, `password` write-only with
  `hasPassword`, `active` never accepted, test email `201 { status, smtp }` or `SMTP_*`
  `502`/`503`/`504` with optional `error.smtp: { code, message }`).
- Error envelope: `docs/agents/specs/api/conventions/errors.md`. Today `ApiClient`'s
  `buildApiError` drops `error.smtp`; the test-email UI needs it, so step 01 carries it on
  `ApiError`.
- Existing patterns to follow:
  - client: `frontend/assets/js/client/OrganizationClient.js` (object module over
    `ApiClient.getJson` / `postJson` / `patchJson` / `deleteJson`; `undefined` result = session
    ended, already handled by `ApiClient`);
  - page: `components/resources/organization/pages/Organization.jsx` + `controllers/` +
    `helpers/` (exported `buildLoadEffect` / `buildFieldUpdate`, controller with injected
    setters and client);
  - `:id` route param: `AdminUserEdit.jsx` (`Router.extractParams('/admin/users/:id/edit', hash)`);
  - list with row actions and injectable `confirm` (defaults to `window.confirm`):
    `AdminUsers.jsx`, `AdminUsersController.js`, `users/DenyUserButton.jsx`;
  - fields / submit errors: `common/forms/helpers/FormFieldsHelper.jsx`;
  - validators: `utils/validation/fieldValidators.js`.
- Decisions from the issue: separate routes (`#/smtp-connections`, `#/smtp-connections/new`,
  `#/smtp-connections/:id/edit`); actions per row on the list; every approved user has access
  (no admin check).
- Specs live under `frontend/specs/assets/js/...`, mirroring the source tree.

## Steps

- [01 — ApiError carries error.smtp](frontend/01-api-error-smtp.md)
- [02 — SmtpConnectionsClient](frontend/02-smtp-connections-client.md)
- [03 — List screen with row actions](frontend/03-smtp-connections-list.md)
- [04 — Create and edit form](frontend/04-smtp-connection-form.md)
- [05 — Routes, pages and header item](frontend/05-routes-and-header.md)
- [06 — Frontend spec docs](frontend/06-frontend-spec-docs.md)

## CI Checks
- `frontend`: `npm run lint` and `npm run coverage` (CI job: `frontend_checks`)

## Notes
- The test email is synchronous and can take up to the send-flow timeouts; keep the modal's
  button disabled and show a "sending…" state while it runs.
- Never put the password into any state that is rendered back, logged, or kept after a
  successful save; clear the password input after save.
- Delete confirmation uses the existing injectable `confirm` pattern (`window.confirm` by
  default) with a different message for the active connection; the test email needs a real
  modal (react-bootstrap `Modal`) because it has an input.
- Trying the screens under `frontend_dev` needs the `*.json` Vite proxy (#46); the specs do
  not.
- Coverage: keep the exported pure helpers (`buildLoadEffect`, field updaters, payload
  builders) testable without a React renderer, as in #42.
