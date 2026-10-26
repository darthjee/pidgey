# Create and edit form
One form page serving both routes, with a mode: `SmtpConnectionForm.jsx` (or
`SmtpConnectionNew.jsx` / `SmtpConnectionEdit.jsx` sharing one controller and helper — pick
whichever keeps files small and coverage simple). The edit id comes from
`Router.extractParams('/smtp-connections/:id/edit', hash)`, as in `AdminUserEdit.jsx`.

Fields: **Name**, **Host**, **Port** (number input), **Encryption** (select: `none`, `tls`,
`ssl`), **Username**, **Password** (`type="password"`, `autocomplete="new-password"`),
**From address**.

Behavior:
- **Edit load:** `client.get(id)` fills every field except the password; show
  "Password: set" / "not set" from `hasPassword`. `404` → "SMTP connection not found" with a
  link back to the list; other failures → load error; `undefined` → idle.
- **Client-side validation** (the API stays authoritative):
  - required after trim: `name`, `host`, `encryption`, `fromAddress`; `port` required;
  - `port` must be an integer 1–65535 (`port must be an integer`,
    `port must be between 1 and 65535`), sent as a JSON number;
  - `name`, `host`, `username`, `fromAddress` at most 255 characters after trimming,
    `password` at most 255 characters, never trimmed;
  - `fromAddress` a valid email (reuse `validateEmail`);
  - pairing: create → username and password both filled or both empty; edit → a typed
    username with no password is fine only when `hasPassword`, and the reverse when a username
    is stored. Message: `username and password must be both set or both empty`, on the
    password field.
  - add shared helpers to `fieldValidators.js` when reusable (e.g. port range, max length).
- **Payload:**
  - create: trimmed strings, numeric port; `username` / `password` only when filled;
  - edit: all non-credential fields; `username` (trimmed) when changed; `password` only when
    typed; **Remove credentials** (shown on edit when credentials exist) sends
    `username: ""` and `password: ""`.
- **Errors:** `400 VALIDATION_FAILED` details mapped to fields by their leading field name
  (`name`, `host`, `port`, `encryption`, `username`, `password`, `fromAddress`); the pairing
  message to `password`; anything unmatched or any other error → submit error.
- **Success:** create → navigate to `#/smtp-connections`; edit → success message, fields
  refilled from the returned item, password input cleared, "set"/"not set" refreshed.
- The create form states that new connections start inactive (activate from the list).
- **Specs:** load (edit) success / 404 / failure / `undefined`; every validation rule;
  payloads for create and edit (password only when typed, remove credentials sends `""` for
  both, numeric port, trimming, password never trimmed); `VALIDATION_FAILED` mapping;
  create redirect; edit refill and password clearing.

## Files to Change
- `frontend/assets/js/components/resources/smtpConnections/pages/SmtpConnectionForm.jsx` (or New/Edit pages) — form page(s).
- `frontend/assets/js/components/resources/smtpConnections/pages/controllers/SmtpConnectionFormController.js` — load, validate, submit.
- `frontend/assets/js/components/resources/smtpConnections/pages/helpers/SmtpConnectionFormHelper.jsx` — form rendering.
- `frontend/assets/js/utils/validation/fieldValidators.js` — optional shared validators.
- `frontend/specs/assets/js/...` — matching specs.
