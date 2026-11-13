# Form rules

Add the two client-side rules of `screens.md#smtp-connection-form` (M8) to
`SmtpConnectionFormRules`.

- **Messages:** export `ENCRYPTION_REQUIRED_MESSAGE` and `REDIRECTION_MESSAGE`, with the texts
  in plan.md.
- **Stored state:** the redirection rule needs the loaded settings. Extend the edit form's
  `stored` value to `{ username, hasPassword, host, port, encryption }`:
  - `SmtpConnectionFormController` calls `setStored` with these fields from the loaded item, and
    again after a successful update;
  - update the JSDoc types wherever `stored` appears;
  - `stored` stays `null` on create.
- **Credentials require encryption:** on the merged state, a credential exists when the trimmed
  username is non-empty, or a password is typed, or `stored.hasPassword`. Remove credentials is
  its own action, so it does not count here. When a credential exists and `fields.encryption` is
  not `tls` or `ssl`, show `ENCRYPTION_REQUIRED_MESSAGE` on `password`.
- **Credential redirection (edit only, `stored` not null):**
  - it applies when the merged state keeps credentials (a username stays or `hasPassword`), no
    new password is typed, and one of these differs from `stored`:
    - the trimmed `host` (exact, case-sensitive);
    - `Number(port)`;
    - `encryption`;
  - then show `REDIRECTION_MESSAGE` on `password`.
- **Precedence:** these checks run only when `password` has no error yet, in this order:
  max-length, then pairing, then encryption, then redirection. So exactly one message shows.
- **Server details:** `mapValidationDetails` maps both messages to `password`, as it does for
  `PAIRING_MESSAGE`. Use a set of password-level messages.
- **Specs:**
  - create with credentials and `none` gives the encryption error, with no call made;
  - `tls` and `ssl` pass;
  - on edit, a host change with stored credentials and no new password gives the redirection
    error;
  - the same change with a typed password passes;
  - changing name, fromAddress or username alone passes;
  - no stored credentials means no redirection error;
  - a case-only host change counts as a change;
  - the server's details map to `password` (use the `validationFailed` fixture);
  - the form controller stores host, port and encryption.

## Files to Change

- `frontend/assets/js/components/resources/smtpConnections/pages/controllers/SmtpConnectionFormRules.js`
  — the two rules, the messages and the mapping.
- `frontend/assets/js/components/resources/smtpConnections/pages/controllers/SmtpConnectionFormController.js`
  — the stored settings.
- `frontend/specs/assets/js/components/resources/smtpConnections/pages/controllers/SmtpConnectionFormRulesSpec.js`
  and `SmtpConnectionFormControllerSpec.js` — the new cases.
