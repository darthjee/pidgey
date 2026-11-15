# Registration and Bootstrap

Purpose: define how organizations and users are created by registration and how the first admin comes to exist.

## Registration flow

1. A person submits a username, an email, a password and an organization name.
2. Pidgey creates a **new** organization and the user (linked to it) in a single
   transaction. The user's `status` is set at insert time:
   - `approved` only when `USER_APPROVAL_REQUIRED` is exactly `false`;
   - `pending` otherwise (the default).
3. A session is started for the new user (access and refresh tokens; mechanics in
   [api/authentication.md](../api/authentication.md)).

Request payload field names (including the required `organizationName`), validation and error
codes are defined by the registration endpoint spec,
[api/endpoints/auth/session.md](../api/endpoints/auth/session.md#post-authregisterjson).

Rules:

- Registration is always open. `USER_APPROVAL_REQUIRED` is the control: unless it is
  exactly `false`, every new user waits for an admin's approval before reaching any protected
  route. It defaults to `true`, because open registration is an SSRF-style vector: a stranger
  gets an organization that can store SMTP connections and make the server open TCP
  connections to arbitrary hosts and ports (see
  [architecture/configuration.md](../architecture/configuration.md)).
- Registration's `USERNAME_TAKEN` / `EMAIL_TAKEN` answers reveal that a user exists, even a
  `denied` one. This is an accepted **known limitation**, the documented exception to
  enumeration safety (see
  [api/endpoints/auth/session.md](../api/endpoints/auth/session.md#known-limitation-enumeration)).
- Registration always creates a new organization; it never joins an existing one (see
  [entities.md](entities.md) for the pending decision on joining).
- A pending user's organization exists but stays unusable until the user is approved, since
  the gate blocks every protected route (see
  [entities.md](entities.md#user-status-lifecycle)).
- The organization is never removed, even if its user is denied.

## First admin

- No endpoint creates or promotes admins: `is_admin` is set out of band, directly in the
  database.
- There is no `demo` seed user.

## Login and sessions

Login, logout, session tokens and the gate are defined in
[api/authentication.md](../api/authentication.md). State-changing requests are protected
against CSRF by the origin check (see
[api/conventions/origin-check.md](../api/conventions/origin-check.md)).

## Pending decisions

- **Pending decision:** the setup flow, to be handled by a future issue: how the first admin
  is created in production, whether a development seed exists, and how the **system
  organization** (the instance's own organization, whose active SMTP connection sends system
  emails such as password recovery) is marked and by whom, e.g. an `is_system` flag on
  `organizations`, at most one, set by the setup flow (see
  [api/endpoints/auth/recovery.md](../api/endpoints/auth/recovery.md#recovery-email)).
