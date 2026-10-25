# 03 — Bootstrap and registration

Rewrite `docs/agents/specs/organizations/bootstrap.md` (rename the title to "Registration and
Bootstrap" if it reads better; keep the file name):

- **Registration flow**: the person submits username, email, password and organization name;
  Pidgey creates the organization and the user (linked to it) in a single transaction, with
  `status` = `pending` when `USER_APPROVAL_REQUIRED=true`, otherwise `approved`; a session is
  started (access + refresh tokens; mechanics in `api/authentication.md`, #27). Payload field
  names and errors: endpoint specs (#28).
- Registration is always open; `USER_APPROVAL_REQUIRED` is the control (resolves the old
  "registration stays open" pending item). It always creates a **new** organization.
- A pending user's organization exists but is unusable until approval (gate).
- **First admin**: no endpoint creates or promotes admins; `is_admin` is set out of band.
  **Pending decision:** how the first admin is created in production (and whether a
  development seed exists) — to be handled by a future issue.
- **Login and sessions**: remove the PHP-session section (password_verify,
  session_regenerate_id, session cookie flags, logout); replace it with a short pointer to
  `api/authentication.md`. Keep the reference to the origin check
  (`api/conventions/origin-check.md`) for state-changing requests.

## Files to Change
- `docs/agents/specs/organizations/bootstrap.md` — rewritten as above.
