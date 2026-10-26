# List screen with row actions
`#/smtp-connections` page, following the `Organization` / `AdminUsers` pattern.

`SmtpConnections.jsx` (page), `controllers/SmtpConnectionsController.js`,
`helpers/SmtpConnectionsHelper.jsx`, plus small components for the row buttons and the
test-email modal.

Behavior:
- **Load** with `client.list()` on mount (exported `buildLoadEffect(controller)`): loading
  state; on failure a load error; `undefined` → stay idle. Empty list → empty-state message.
- **Rows:** name (link to `#/smtp-connections/{id}/edit`), `host:port`, encryption,
  `fromAddress`, credentials ("set" when `username` is not null or `hasPassword`, else
  "none"), and an **Active** badge on the active one. A **New connection** button links to
  `#/smtp-connections/new`.
- **No active connection** notice when the list is non-empty and none has `active: true`
  (sending is disabled until one is activated). Also show it when the list is empty.
- **Activate** (hidden on the active row): `client.activate(id)`; on success set
  `active = (row.id === id)` on every row from the returned item; errors are shown as a list
  error.
- **Delete:** `confirm(message)` (injectable, defaults to `window.confirm`); the message for
  the active connection warns that sending stops (`POST /v1/messages` answers
  `409 NO_SMTP_CONNECTION`) until another connection is activated. On confirm,
  `client.remove(id)`; on success remove the row. Errors shown as a list error.
- **Send test email:** button opens `SmtpConnectionTestModal` for that row, with an optional
  **Recipient** input (help text: empty sends to your own email). Submit →
  `client.test(id, { to: trimmed or undefined })`; the button is disabled while sending.
  - `201`: success message with `smtp.code` and `smtp.message`;
  - `ApiError` with an `SMTP_*` code: the error message plus `error.smtp` code/message when
    present;
  - `400 VALIDATION_FAILED`: details on the recipient field;
  - other errors: generic error in the modal;
  - closing the modal resets its state.
- **Specs** (controller, helper, page exports, components): load success / failure /
  `undefined`, empty state, the no-active notice, badge and Activate visibility, activate
  updates the active flags, delete with confirm declined / accepted / active-warning message /
  failure, test-email success, `SMTP_*` failure with and without `error.smtp`,
  `VALIDATION_FAILED` on recipient, `to` omitted when blank, busy state.

## Files to Change
- `frontend/assets/js/components/resources/smtpConnections/pages/SmtpConnections.jsx` — list page.
- `frontend/assets/js/components/resources/smtpConnections/pages/controllers/SmtpConnectionsController.js` — load, activate, delete, test.
- `frontend/assets/js/components/resources/smtpConnections/pages/helpers/SmtpConnectionsHelper.jsx` — list rendering.
- `frontend/assets/js/components/resources/smtpConnections/pages/connections/` — row buttons and `SmtpConnectionTestModal.jsx`.
- `frontend/specs/assets/js/components/resources/smtpConnections/...` — matching specs.
