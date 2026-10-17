# Bootstrap, Registration and Login

Purpose: define how the first user and organization are created and how users authenticate in the admin UI.

## Bootstrap flow

1. The first person to register submits username, password and organization name.
2. Pidgey creates the organization and the user (linked to it) in a single transaction.
3. The user is logged in (session started).

**Pending decision:** whether public registration remains open after the first
organization exists (multi-tenant signup) or is closed/configurable.

## Login

- Classic PHP session: username/password form → server verifies with
  `password_verify()` → `session_regenerate_id(true)` → session cookie.
- Cookie flags: `HttpOnly`, `SameSite=Lax`, `Secure` in production.
- Logout destroys the session.
- Passwords stored with `password_hash()` (`PASSWORD_DEFAULT`).
- State-changing session requests require CSRF protection
  (**Pending decision:** token header vs. relying on SameSite + JSON content type).
