# Frontend Plan: Frontend: show the organization in the admin users list

Issue: [45-frontend-show-the-organization-in-the-admin-users-list.md](../../issues/45-frontend-show-the-organization-in-the-admin-users-list.md)

## Overview
Show each user's organization in the Admin Users list (`#/admin/users`) as a new **Organization** column, after **Email**, reading `organizationName` from the admin user item. Nothing else on the screen changes. The Admin User Edit screen is out of scope (it never loads the target user).

## Context
- The [admin user item](../../specs/api/endpoints/admin-users.md#admin-user-item) returned by `POST /admin/users/search.json` carries `organizationId` and `organizationName`; the UI ignores them today.
- `docs/agents/specs/frontend/api-compatibility.md` ("Admin users" section) records this as the optional follow-up #45.
- The table is rendered by `renderUsersTable` / `renderRow` in `AdminUsersHelper.jsx`. `AdminUsersController` and `AdminClient.searchUsers` already pass the whole user object through, so no client or controller change is needed.

## Implementation Steps

### Step 1 — Organization column and specs
In `AdminUsersHelper.jsx`:
- add `<th>Organization</th>` between `<th>Email</th>` and `<th>Admin</th>` in `renderUsersTable`;
- add `<td>{user.organizationName}</td>` between the email and admin cells in `renderRow`. Render it as a plain JSX expression (React escapes it); a missing or `null` value renders an empty cell, so no fallback text is needed;
- add `organizationName: (string|null)` to the user shape in the `renderRow` JSDoc.

Do not show `organizationId`. Do not change the search form, the row actions or the other columns.

In `AdminUsersHelperSpec.js` (same `renderPage` / `buildState` helpers and `renderedOutput` matchers as the existing specs), add:
- the header has an Organization column, after Email and before Admin (check the order in the rendered markup, e.g. by comparing `indexOf` of the `<th>` texts);
- a row with `organizationName: "Bob's Org"` shows that name in a cell;
- a row without `organizationName` (and one with `null`) renders without errors and still shows the other cells.

Update any existing spec that asserts the full header row or the exact row markup, if one exists.

### Step 2 — Frontend spec docs
- `docs/agents/specs/frontend/api-compatibility.md`, "Admin users" section: replace the paragraph saying the UI ignores `organizationId`/`organizationName` (follow-up #45) with one saying the Admin Users list shows `organizationName` in an Organization column and ignores `organizationId`.
- `docs/agents/specs/frontend/screens.md`: the `#/admin/users` row of the routes table also mentions that the list shows each user's organization.

## Files to Change
- `frontend/assets/js/components/resources/admin/pages/helpers/AdminUsersHelper.jsx` — Organization header and cell; JSDoc.
- `frontend/specs/assets/js/components/resources/admin/pages/helpers/AdminUsersHelperSpec.js` — specs for the new column.
- `docs/agents/specs/frontend/api-compatibility.md` — drop the #45 gap note.
- `docs/agents/specs/frontend/screens.md` — mention the organization in the Admin Users route row.

## CI Checks
- `frontend`: `npm run lint` and `npm run coverage` (CI job: `frontend_checks`)

## Notes
- Out of scope: the Admin User Edit screen, filtering or searching by organization, linking the name, and any API change.
- The table gets wider; no responsive change is required by the issue.
