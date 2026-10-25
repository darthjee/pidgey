# Registration and Bootstrap

Purpose: define how organizations and users are created by registration and how the first admin comes to exist.

## Registration flow

1. A person submits a username, an email, a password and an organization name.
2. Pidgey creates a **new** organization and the user (linked to it) in a single
   transaction. The user's `status` is set at insert time:
   - `pending` when `USER_APPROVAL_REQUIRED=true`;
   - `approved` otherwise.
3. A session is started for the new user (access and refresh tokens; mechanics in
   [api/authentication.md](../api/authentication.md), #27).

Request payload field names, validation and error codes are defined by the registration
endpoint spec (#28).

Rules:

- Registration is always open. `USER_APPROVAL_REQUIRED` is the control: when it is `true`,
  every new user waits for an admin's approval before reaching any protected route.
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

- **Pending decision:** how the first admin is created in production, and whether a
  development seed exists. To be handled by a future issue.
