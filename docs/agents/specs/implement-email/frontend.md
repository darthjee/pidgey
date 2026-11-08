# Implement Email: Frontend Alignment

Purpose: map the wiring of the existing SMTP connection screens (#43) to the real backend;
the frontend alignment decisions owned by #154 are settled in the permanent specs linked
below.

> Temporary — part of epic #149. Permanent specs win on conflict; update them, not this
> file, when a decision changes the target behavior.

Index: [implement-email.md](../implement-email.md).

## Open items

All settled by #154.

- [x] **Divergences with the real API:** the survey of the #43 screens found the field names
  (`fromAddress`, `hasPassword`, `username: null`), the `smtpConnections` / `smtpConnection`
  keys, the error envelope (`error.details` mapped to fields, `error.smtp` on `SMTP_*`
  failures), the status codes (`201` on the test email, `204` on delete, the `404` body)
  aligned with the API spec, and no HTML rendering. The gaps, now specified:
  - no `deactivate` client method or action → a **Deactivate** action on the active row,
    with confirmation, then a list refresh
    ([screens.md](../frontend/screens.md#smtp-connections-list));
  - no `429` handling → the test modal shows `error.message`, `Retry-After` ignored in v1
    ([screens.md](../frontend/screens.md#test-email-modal));
  - no client-side "credentials require encryption" or credential-redirection rule → both
    mirrored with the server's messages, the redirection error on the password field
    ([screens.md](../frontend/screens.md#smtp-connection-form));
  - the test-email help text → says the recipient must be the email of a user of the
    organization ([screens.md](../frontend/screens.md#test-email-modal));
  - the compatibility table marked every SMTP row ✅ → a deactivate row and the open
    mismatches M7–M9 ([api-compatibility.md](../frontend/api-compatibility.md#pidgey-admin)).

  Out of scope: links from `SMTP_PASSWORD_UNREADABLE` or `SMTP_INSECURE_CREDENTIALS` to the
  edit form.
- [x] **SMTP reply display:** `error.smtp.message` and `smtp.message` are rendered as text
  only and wrapped (`text-break`), never truncated client-side, since the server caps them at
  512 characters — [screens.md](../frontend/screens.md#test-email-modal),
  [email/failures.md](../email/failures.md#sanitization-and-length-cap).
- [x] **Definition of done:** the Jasmine specs use shared JSON fixtures under
  `frontend/specs/support/fixtures/smtpConnections/`, hand-maintained copies of the API
  spec's example responses (not captured from the backend), updated in the same PR as the
  spec example they mirror; `api-compatibility.md` is accurate; and a manual end-to-end
  check against the development `mailpit` passes (create, edit, activate, deactivate, test
  email seen in the Mailpit UI, a `400` and a `502` path, delete) —
  [frontend/testing.md](../frontend/testing.md#smtp-connections-definition-of-done).

## Building block: frontend wiring

- **Responsibility:** makes the SMTP connection screens (list, form, test-email modal) work
  against the real `/smtp-connections*.json` endpoints, aligning client, screens and
  compatibility table with the backend. It adds no new screen.
- **Proposed names:** the existing `frontend/assets/js/client/SmtpConnectionsClient.js` and
  `frontend/assets/js/components/resources/smtpConnections/` (pages, controllers, helpers,
  including `SmtpConnectionFormRules`); the fixtures under
  `frontend/specs/support/fixtures/smtpConnections/`.
- **Collaborators:** `ApiClient`, `ApiError` (with `smtp`), the routes in
  `frontend/assets/js/App.jsx`, the backend endpoints ([endpoints.md](endpoints.md)), the
  development `mailpit` (manual check).
- **Governing specs:** [frontend/screens.md](../frontend/screens.md#smtp-connections-screens);
  [frontend/layout.md](../frontend/layout.md) (`SmtpConnectionsClient`, `ApiError`);
  [frontend/api-compatibility.md](../frontend/api-compatibility.md);
  [frontend/testing.md](../frontend/testing.md);
  [api/endpoints/pidgey-admin/smtp-connections.md](../api/endpoints/pidgey-admin/smtp-connections.md).
- **Open items:** none.

## Implementation work

Names are **proposals** (see the lifecycle rules in [implement-email.md](../implement-email.md)).

1. **Client** — `SmtpConnectionsClient.deactivate(id)`: `POST
   /smtp-connections/:id/deactivate.json`, no request body
   ([layout.md](../frontend/layout.md)).
2. **Deactivate action** — on the active row only, with confirmation, then a list refresh
   ([SMTP connections list](../frontend/screens.md#smtp-connections-list)).
3. **Form rules** — `SmtpConnectionFormRules` additions: credentials require `tls` / `ssl`
   on the merged state, and credential redirection on edit
   ([SMTP connection form](../frontend/screens.md#smtp-connection-form)).
4. **Test-email modal** — `429` shown as `error.message`; `text-break` on `smtp.message` and
   `error.smtp.message`; the recipient help text
   ([Test-email modal](../frontend/screens.md#test-email-modal)).
5. **Fixtures** — the shared JSON fixtures, and the specs moved onto them
   ([API response fixtures](../frontend/testing.md#api-response-fixtures)).
6. **Compatibility table** — clear M7–M9 and mark the SMTP rows ✅ once they match
   ([api-compatibility.md](../frontend/api-compatibility.md#pidgey-admin)).
7. **Manual check** — the end-to-end check against the development `mailpit`
   ([definition of done](../frontend/testing.md#smtp-connections-definition-of-done)).
