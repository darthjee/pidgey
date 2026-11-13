# Test-email modal

Align the test-email modal with `screens.md#test-email-modal` (M9).

- **`429`:** `SmtpConnectionTestController`'s default branch already sets `error.message`. Add
  a spec with the `tooManyRequests` fixture that shows the message and sets no `smtp` and no
  `fieldError`. Also cover the `sendTimeFailure` (409) and `smtpFailure` (502 with
  `error.smtp`) fixtures.
- **Wrapping:** in `SmtpConnectionTestForm.jsx`, add the class `text-break` to the elements that
  render the SMTP reply, both in the success alert and in the error's `renderErrorSmtp` block.
  Wrap each reply in its own element, for example a `<span className="text-break">`, so the
  class targets the reply. The reply is rendered as a JSX text child only, never with
  `dangerouslySetInnerHTML`, and is never truncated.
- **Help text:** replace the recipient help text with: "Leave empty to send the test email to
  your own email address. The recipient must be the email of a user of the organization."
- **Specs:**
  - the reply has `text-break`;
  - a 600-character reply renders in full;
  - a reply holding `<b>x</b>` renders as literal text;
  - the new help text;
  - the `429`, `409` and `502` outcomes, from the fixtures.

## Files to Change

- `frontend/assets/js/components/resources/smtpConnections/pages/connections/SmtpConnectionTestForm.jsx`
  — `text-break` and the help text.
- `frontend/specs/assets/js/components/resources/smtpConnections/pages/connections/SmtpConnectionTestFormSpec.js`
  and `.../controllers/SmtpConnectionTestControllerSpec.js` — the new cases.
