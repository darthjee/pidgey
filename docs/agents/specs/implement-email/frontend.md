# Implement Email: Frontend Alignment

Purpose: map the wiring of the existing SMTP connection screens (#43) to the real backend and
list the frontend alignment decisions owned by #154.

> Temporary — part of epic #149. Permanent specs win on conflict; update them, not this
> file, when a decision changes the target behavior.

Index: [implement-email.md](../implement-email.md).

## Open items

- [ ] **Pending decision:** divergences between the #43 screens / `SmtpConnectionsClient` and
  the real API. The screens were built against the spec and never run against a backend, yet
  [frontend/api-compatibility.md](../frontend/api-compatibility.md) marks every
  `/smtp-connections*.json` row as compatible. Points to compare (by #154, not here): field
  names (`fromAddress`, `hasPassword`, `username: null`), the list key `smtpConnections` and
  item key `smtpConnection`, the error envelope (`error.details` mapped to fields,
  `error.smtp` on `SMTP_*` failures), `201` on the test email and `204` on delete, the
  `404` body, and any rule decided by #151–#153 that changes the API (e.g. credential
  redirection, rate limit `429`, a `deactivate` endpoint). Each divergence found is fixed in
  the permanent specs or the code, and the compatibility table updated.
- [ ] **Pending decision:** `error.smtp.message` (and the success `smtp.message`) come from a
  remote SMTP server: they are rendered as **text only**, never as HTML, and how long values
  are displayed (wrapping, truncation) once the server-side length cap is decided
  ([transport.md](transport.md)).
- [ ] **Pending decision:** definition of done for the wiring: Jasmine specs built on the real
  response shapes (fixtures taken from the backend, not hand-written), plus a manual
  end-to-end check in `make dev` (create, edit, activate, test email through Mailpit, delete),
  per [testing/definition-of-done.md](../testing/definition-of-done.md) and
  [frontend/testing.md](../frontend/testing.md).

## Building block: frontend wiring

- **Responsibility:** makes the SMTP connection screens (list, form, test-email modal) work
  against the real `/smtp-connections*.json` endpoints, aligning client, screens and
  compatibility table with the backend. It adds no new screen.
- **Proposed names:** the existing `frontend/assets/js/client/SmtpConnectionsClient.js` and
  `frontend/assets/js/components/resources/smtpConnections/` (pages, controllers, helpers);
  no new names expected.
- **Collaborators:** `ApiClient`, `ApiError` (with `smtp`), the routes in
  `frontend/assets/js/App.jsx`, the backend endpoints ([endpoints.md](endpoints.md)).
- **Governing specs:** [frontend/screens.md](../frontend/screens.md#smtp-connections-screens);
  [frontend/layout.md](../frontend/layout.md) (`SmtpConnectionsClient`, `ApiError`);
  [frontend/api-compatibility.md](../frontend/api-compatibility.md);
  [api/endpoints/pidgey-admin/smtp-connections.md](../api/endpoints/pidgey-admin/smtp-connections.md).
- **Open items:** every item above.
