# Organization Scope

Purpose: list what belongs to an organization and how isolation is enforced.

Organization-scoped resources:
- Users
- SMTP connections (see [email/smtp-connections.md](../email/smtp-connections.md))
- API tokens (see [api/authentication.md](../api/authentication.md))

Isolation rules:
- The current organization is derived from the authenticated principal (authenticated user
  or API token), never from request input.
- Every repository query on these resources filters by `organization_id`.
- Accessing another organization's resource returns `404`, not `403`.

Admin exception:
- The admin's user-management endpoints (`/admin/users/*`, see
  [entities.md](entities.md#admin)) are the **only** cross-organization access, and only over
  users.
- An admin's SMTP connections and API tokens are still limited to their own organization,
  like any other user's.
