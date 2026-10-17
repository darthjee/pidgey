# Entities and Relationships

Purpose: define organizations and users and how they relate.

- **Organization** has many Users, many SMTP Connections, many API Tokens.
- **User** belongs to **exactly one** Organization.
- **Equal access policy:** all users of an organization have the same permissions.
  There are no roles, no owner/admin distinction.
- A user can see and manage only their own organization's data.

**Pending decisions:**
- How additional users join an existing organization (invitation flow vs. created by
  an existing user). Until decided, only an existing user creating another user is assumed.
- Whether deleting users/organizations is supported in v1.
