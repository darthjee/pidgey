# 04 — Scope and terminology

`docs/agents/specs/organizations/scope.md`:
- "session user" → "authenticated user"; the current organization is derived from the
  authenticated principal (user or API token), never from request input.
- Add: the admin's user-management endpoints are the only cross-organization access, and
  only over users; an admin's SMTP connections and API tokens are still limited to their own
  organization.

`docs/agents/specs/overview/terminology.md`:
- Update **Organization** (created at registration with its first user) and **User**
  (username + email, belongs to one organization, has a status).
- Add **Admin**, **User status**, **Session** (a login, identified by a session id, carried
  by the token pair), **Access token** (short-lived credential in the `access_token` cookie),
  **Refresh token** (long-lived, stored hashed, rotated), **Authorization request** (device
  authorization: a pending login approved from another, logged-in session). Link to
  `api/authentication.md` where mechanics live. Keep **API Token** distinct from the access
  and refresh tokens.

## Files to Change
- `docs/agents/specs/organizations/scope.md` — wording and admin note.
- `docs/agents/specs/overview/terminology.md` — new and updated terms.
