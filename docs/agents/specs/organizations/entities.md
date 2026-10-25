# Entities and Relationships

Purpose: define organizations, users and admins, how they relate, and the user status lifecycle.

## Organization

- Has many Users, many SMTP Connections and many API Tokens.
- Created at registration, together with its first user, in the same transaction (see
  [bootstrap.md](bootstrap.md)).
- Never deleted in v1.

## User

- Belongs to **exactly one** Organization: `organization_id` is required for every user,
  admins included.
- Identified by a unique `username` (used to log in) and a unique `email` (used for account
  recovery). Both are required.
- Has a `status` (see [User status lifecycle](#user-status-lifecycle)) and an `is_admin` flag
  (see [Admin](#admin)).
- Never deleted in v1: denying a user replaces removing them.

## Equal access policy

- All users of an organization have the same permissions over that organization's data.
  There are no roles and no owner.
- A user can see and manage only their own organization's data (see [scope.md](scope.md)).

## Admin

- A user with `is_admin = true` is a **global instance admin**. The admin is still a normal
  member of one organization and has the same access as any other member of it.
- The **only** extra power is user management across all users of the instance: search,
  edit, approve, deny, and generate a recovery link or send a recovery email (the
  `/admin/users/*` endpoints, specified in the endpoint specs, #30).
- The admin has **no** access to other organizations' SMTP connections or API tokens:
  organization isolation applies to them like to anyone else.
- No endpoint creates or promotes admins: `is_admin` is set out of band (see
  [bootstrap.md](bootstrap.md) for the first admin).

## User status lifecycle

`status` is one of `pending`, `approved`, `denied`.

- New registrations start `pending` when `USER_APPROVAL_REQUIRED=true`, otherwise
  `approved`.
- Only `approved` users pass the gate and can reach protected routes.
- `pending` users can log in (they get a real session) but are blocked from every protected
  route until an admin approves them.
- `denied` users look like unknown users everywhere, except a login with the **correct**
  password, which is answered with a dedicated error (code defined by the endpoint specs).
- An admin moves users between statuses (approve, deny; specified in #30).
- The gate mechanics (what is checked on each request, how a session is affected) are
  defined in [api/authentication.md](../api/authentication.md) (#27).

A denied or pending user's organization is kept: it is never removed, and it stays unused
while its user cannot pass the gate. The organization's API tokens are not affected by the
user's status.

## Pending decisions

- **Pending decision:** how additional users join an existing organization (invitation flow,
  creation by an existing member, or another mechanism). Registration always creates a new
  organization, so until this is decided an organization effectively has one user.
- **Pending decision:** whether denying a user revokes their organization's API tokens. For
  now they keep working.
