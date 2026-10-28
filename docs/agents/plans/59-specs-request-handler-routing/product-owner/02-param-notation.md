# Convert path-param notation

Rewrite **API route paths** in the specs from `{id}` / `{uuid}` to `:id` / `:uuid`, e.g. `POST /admin/users/{id}/approve.json` → `POST /admin/users/:id/approve.json`. The endpoint specs then read the same way as the route files.

- Only change API paths (method + path, or `*.json` paths).
- Leave other placeholders alone:
  - `{FRONTEND_BASE_URL}`, `{token}` and other template/URL placeholders, e.g. in `auth/recovery.md` and `admin-users/recovery.md`;
  - frontend hash routes in `frontend/screens.md` and `frontend/layout.md`, unless the placeholder sits inside an API path.
- Check each hit by reading its context. Don't run a blind global replace.

Known hits (from `grep -rE '\{(id|uuid)\}' docs/agents/specs`):

## Files to Change
- `docs/agents/specs/api/endpoints/admin-users.md`
- `docs/agents/specs/api/endpoints/admin-users/edit.md`
- `docs/agents/specs/api/endpoints/admin-users/recovery.md`
- `docs/agents/specs/api/endpoints/admin-users/review.md`
- `docs/agents/specs/api/endpoints/auth/device-authorization.md`
- `docs/agents/specs/api/endpoints/auth/device-authorization/approver.md`
- `docs/agents/specs/api/endpoints/auth/device-authorization/requester.md`
- `docs/agents/specs/api/endpoints/pidgey-admin.md`
- `docs/agents/specs/api/endpoints/pidgey-admin/api-tokens.md`
- `docs/agents/specs/api/endpoints/pidgey-admin/organization.md`
- `docs/agents/specs/api/endpoints/pidgey-admin/smtp-connections.md`
- `docs/agents/specs/frontend/api-compatibility.md` — API paths only.
- `docs/agents/specs/frontend/screens.md`, `docs/agents/specs/frontend/layout.md` — only if the hit is an API path.
