# 01 — Entities

Rewrite `docs/agents/specs/organizations/entities.md` for the merged model:

- **Organization** has many Users, SMTP connections and API tokens; created at registration
  together with its first user; never deleted in v1.
- **User** belongs to exactly one Organization (`organization_id` required for every user,
  admins included); identified by a unique `username` and a unique `email`.
- **Equal access policy** within an organization: no roles, no owner.
- **Admin** (`is_admin`): a global instance admin who is still a normal member of one
  organization. The only extra power is user management across all users (search, edit,
  approve, deny, recovery link/email — the `/admin/users/*` endpoints, specified in #30). No
  access to other organizations' SMTP connections or API tokens. No endpoint creates or
  promotes admins.
- **User status lifecycle**: `pending` / `approved` / `denied`. New registrations start
  `pending` when `USER_APPROVAL_REQUIRED=true`, otherwise `approved`. Only `approved` users
  pass the gate; `pending` users can log in but are blocked from every protected route;
  `denied` users look like unknown users, except a login with the correct password (error
  code defined by the endpoint specs). An admin moves users between statuses (#30). Link to
  `api/authentication.md` for the gate mechanics (#27).
- No deletion of users or organizations in v1 (deny replaces removing a user).

Pending decisions:
- How additional users join an existing organization (registration always creates a new
  one; until decided, an organization effectively has one user).
- Whether denying a user revokes their organization's API tokens (they keep working for now).

Remove the resolved pending items (deletion; "only an existing user creating another user").

## Files to Change
- `docs/agents/specs/organizations/entities.md` — rewritten as above.
