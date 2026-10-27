# Issue: Frontend: show the organization in the admin users list

## Description

The Admin Users screen (`#/admin/users`) lists users from every organization, since an instance admin manages users across organizations. Each user item in the API response carries `organizationId` and `organizationName` ([admin user item](../specs/api/endpoints/admin-users.md#admin-user-item)), but the screen copied from Citadel ignores them.

## Problem

- An admin cannot tell which organization a user belongs to before approving, denying or editing them.
- `docs/agents/specs/frontend/api-compatibility.md` records this gap as the optional follow-up #45.

Found while checking frontend/API compatibility in #32 (epic #24). The API specs under `docs/agents/specs/api/**` are the contract; this issue changes frontend code and frontend specs only.

## Expected Behavior

- The users table in `AdminUsers` gets an **Organization** column, placed after **Email**, showing the user's `organizationName` as plain text.
  - React escapes the value, so a name with HTML or quotes is shown as text.
  - If the field is missing or `null` (for example an older API), the cell is empty and nothing breaks.
- The other columns, the row actions (Edit, Generate link, Send email, Approve, Deny) and the search form do not change.
- `organizationId` is not shown.

## Solution

### Changes

- `frontend/assets/js/components/resources/admin/pages/helpers/AdminUsersHelper.jsx`:
  - add the `<th>Organization</th>` header after Email;
  - add `<td>{user.organizationName}</td>` in `renderRow`;
  - update the JSDoc of the user shape.
- `docs/agents/specs/frontend/api-compatibility.md`: replace the "Admin users" note about #45 with a sentence saying the list shows `organizationName` and ignores `organizationId`.
- `docs/agents/specs/frontend/screens.md`: the `#/admin/users` row in the routes table mentions that the list shows each user's organization.

### Testing

Jasmine specs for `AdminUsersHelper`:

- the table header has the Organization column in the expected position;
- a row shows the user's `organizationName`;
- a row with no `organizationName` renders an empty cell without errors.

CI: `npm run lint` and `npm run coverage` (job `frontend_checks`).

### Out of scope

- The `AdminUserEdit` screen. It never loads the target user (there is no endpoint that reads one admin user, and its fields start empty), so it cannot show the organization before a save. Revisit if a load endpoint is added.
- Searching or filtering by organization: `q` only matches `username` and `email` in the API.
- Linking the organization name to another screen.
- Any API change.

## Benefits

- An admin sees each user's organization before approving, denying, or editing them.
- The admin users part of the compatibility check no longer lists an open gap.
