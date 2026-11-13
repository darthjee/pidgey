# Deactivate

Add the Deactivate action (M7).

- **Client:** `SmtpConnectionsClient.deactivate(id)` returns
  `ApiClient.postJson(itemPath(id, 'deactivate'))`, with no body, mirroring `activate`. It gets
  a JSDoc, and the `itemPath` JSDoc lists `deactivate` too.
- **Controller:** `SmtpConnectionsController` gets
  `export const DEACTIVATE_CONFIRM_MESSAGE` (the text in plan.md) and
  `async handleDeactivate(id)`:
  1. return when `this.confirm(DEACTIVATE_CONFIRM_MESSAGE)` is false, sending nothing;
  2. `setListError(null)`;
  3. `await this.client.deactivate(id)`, then return on `undefined` (session ended);
  4. reload the list with `await this.load()`, the "refresh" of `screens.md`;
  5. on an error, call `setListError(error.message)`.
- **Button:** add `connections/DeactivateConnectionButton.jsx`, the mirror of
  `ActivateConnectionButton`. It renders only when `connection.active`, as
  `btn btn-sm btn-warning me-2`, labeled "Deactivate".
- **Wiring:**
  - render the button in `SmtpConnectionsHelper.renderRow`, next to Activate;
  - add the handler `onDeactivate: (id) => () => controller.handleDeactivate(id)` in
    `SmtpConnections.jsx`;
  - update the row JSDoc.
- **Specs:**
  - the client sends a `POST` to the path, with no body;
  - the controller: cancelled sends nothing; success calls `list` again and updates the
    connections; `undefined` returns early; an error is set as the list error (use the
    `notFound` fixture);
  - the button: hidden on an inactive row, and the click calls the handler;
  - the helper and the page render the button on the active row only.

## Files to Change

- `frontend/assets/js/client/SmtpConnectionsClient.js` — `deactivate(id)`.
- `frontend/assets/js/components/resources/smtpConnections/pages/controllers/SmtpConnectionsController.js`
  — the message and `handleDeactivate`.
- `frontend/assets/js/components/resources/smtpConnections/pages/connections/DeactivateConnectionButton.jsx`
  — new.
- `frontend/assets/js/components/resources/smtpConnections/pages/helpers/SmtpConnectionsHelper.jsx`
  — render the button.
- `frontend/assets/js/components/resources/smtpConnections/pages/SmtpConnections.jsx` — the
  `onDeactivate` handler.
- The matching specs under `frontend/specs/assets/js/...`, plus the new
  `DeactivateConnectionButtonSpec.js`.
