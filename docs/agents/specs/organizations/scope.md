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
  [entities.md](entities.md#admin)) are the **only** direct cross-organization access, and
  only over users.
- No admin endpoint reads or edits another organization's SMTP connections or API tokens: an
  admin's direct access to them is limited to their own organization, like any other user's.
- This is not full isolation from the admin: user management includes account recovery, so
  the admin can take over any user's account and thus reach any organization indirectly. The
  admin is trusted with every account (see [entities.md](entities.md#admin), including the
  pending decision on limiting this).
