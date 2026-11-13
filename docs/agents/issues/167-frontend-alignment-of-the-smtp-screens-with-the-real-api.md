# Issue: Frontend alignment of the SMTP screens with the real API

## Description
Part of epic #149 (email integration). Aligns the existing frontend SMTP connection screens
(#43) with the real `/smtp-connections*.json` API, as settled by #154. No new screen is added.

Specs:

- `docs/agents/specs/frontend/screens.md#smtp-connections-screens`;
- `docs/agents/specs/frontend/layout.md` (`SmtpConnectionsClient`, `ApiError`);
- `docs/agents/specs/frontend/testing.md` (API response fixtures, SMTP connections definition of done);
- `docs/agents/specs/frontend/api-compatibility.md#pidgey-admin` (M7–M9);
- `docs/agents/specs/api/endpoints/pidgey-admin/smtp-connections.md`;
- implementation notes: `docs/agents/specs/implement-email/frontend.md` (items 1–7).

## Problem
The #43 screens were built against the spec before the backend existed. The #154 survey found
gaps still open in `api-compatibility.md`:

- **M7:** `SmtpConnectionsClient` has no `deactivate`, and the list has no Deactivate action.
- **M8:** `SmtpConnectionFormRules` has no "credentials require encryption" rule, and no
  credential-redirection rule on edit.
- **M9:** the test modal does not handle `429 TOO_MANY_REQUESTS`, does not wrap long SMTP
  replies, and its help text omits the organization-user rule for the recipient.
- Specs build API responses inline instead of using shared fixtures.

## Expected Behavior
- **Client:** `SmtpConnectionsClient.deactivate(id)` sends `POST /smtp-connections/:id/deactivate.json`
  with no body.
- **List:** a **Deactivate** action on the active row only. It asks for confirmation first
  (through the controller's injectable `confirm`, as Delete does), warning that sends fail
  until a connection is activated. The list refreshes afterwards.
- **Form rules (`SmtpConnectionFormRules`):**
  - credentials (`username` / `password`) are allowed only with `tls` / `ssl`, checked
    on the merged state;
  - on edit, changing host, port or encryption on a connection that has credentials needs a
    new password or Remove credentials, with the error shown on the password field;
  - both rules use the server's messages.
- **Test-email modal:**
  - `429` shows `error.message`; `Retry-After` is ignored in v1;
  - `smtp.message` and `error.smtp.message` are rendered as text only and wrapped
    (`text-break`), never truncated;
  - the help text says the recipient must be the email of a user of the organization.
- **Fixtures:**
  - shared JSON fixtures live under `frontend/specs/support/fixtures/smtpConnections/`,
    one per response shape: item, list, `400 VALIDATION_FAILED`, `SMTP_*` with
    `error.smtp`, `409` send-time failure, `429`, `404`;
  - each is a hand-maintained copy of an example response in
    `api/endpoints/pidgey-admin/smtp-connections.md`. Today that spec only has examples for the
    item and the test-email success, so the same PR adds example bodies for the list, `400`,
    `404`, `409`, `429` and an `SMTP_*` failure with `error.smtp`, using the messages
    it already lists;
  - the SMTP specs load them instead of building responses inline.
- **Compatibility table:** M7–M9 cleared, and the SMTP rows marked ✅ only where they match.

Not in scope: links from `SMTP_PASSWORD_UNREADABLE` / `SMTP_INSECURE_CREDENTIALS` to the
edit form.

## Acceptance
- `make` frontend checks pass (Jasmine, ESLint); the SMTP specs use the fixtures.
- The manual end-to-end check is a **pre-merge checklist in the PR**, run by the owner once
  #166 is merged. It does not block the autonomous run, and item 7 of
  `implement-email/frontend.md` stays unticked until it passes. The check, from
  `frontend/testing.md#smtp-connections-definition-of-done`, runs in `make dev-up` against
  the dev `mailpit`:
  - create, edit, activate, deactivate and delete;
  - a test email, seen in the Mailpit UI;
  - a `400` path and a `502` path (a bad host).
- Items 1–6 of `implement-email/frontend.md` are ticked in the same PR.

## Dependencies
#164 (SMTP connection endpoints, merged). The manual check also needs #166 (test email
endpoint). Agents: frontend (implementation), product-owner (API spec examples,
`api-compatibility.md`, `implement-email/frontend.md`).
