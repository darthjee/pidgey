# Frontend screens and API compatibility

Apply issue decisions 7–11 and 14 to the frontend specs.

- **`frontend/screens.md`, "SMTP connections screens":**
  - fix "the seven endpoints" to eight, and name `deactivate`;
  - **list:** a **Deactivate** action on the active row only. It asks for confirmation,
    warning that sending stops until another connection is activated, calls
    `SmtpConnectionsClient.deactivate(id)` and refreshes the list;
  - **test-email modal:**
    - `429` is listed among the outcomes, showing `error.message`; `Retry-After` is ignored
      in v1;
    - `error.smtp.message` and `smtp.message` are rendered as text only and wrapped
      (`text-break`), never truncated client-side (the server caps them at 512 characters,
      `email/failures.md#sanitization-and-length-cap`);
    - the help text says the recipient must be the email of a user of the organization;
  - **form**, client-side rules mirroring the server, with the server's message texts:
    - credentials only with `tls` or `ssl`, on the merged state;
    - on edit, with stored credentials, a change of host, port or encryption (compared with
      the loaded values) requires a new password or Remove credentials. The error lands on
      the password field: `password must be sent again when host, port or encryption changes`.
  - **out of scope:** no link from `SMTP_PASSWORD_UNREADABLE` or `SMTP_INSECURE_CREDENTIALS`
    to the edit form.
- **`frontend/layout.md`:** `SmtpConnectionsClient` lists `deactivate`, if the file
  enumerates the client methods.
- **`frontend/api-compatibility.md`, Pidgey admin table:**
  - add the `POST /smtp-connections/:id/deactivate.json` row (`SmtpConnectionsClient.deactivate`,
    `200`);
  - note `429` on the test row and the client rules on create / update;
  - mark the rows as the target once the frontend issue lands, not ✅ today: deactivate is
    missing, the client rules are missing, and the rows were never run against a backend.
    Use the file's existing convention for "not yet compatible".

## Files to Change

- `docs/agents/specs/frontend/screens.md` — deactivate, `429`, reply display, help text,
  client rules.
- `docs/agents/specs/frontend/layout.md` — `deactivate` on the client, if listed.
- `docs/agents/specs/frontend/api-compatibility.md` — the deactivate row and accurate
  status.
