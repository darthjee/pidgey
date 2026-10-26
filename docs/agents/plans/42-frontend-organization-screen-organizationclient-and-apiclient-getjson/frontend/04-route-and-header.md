# Route, page registration and header dropdown
Wire the screen into the app shell:

- `HashRouteResolver.js`: add `['/organization', 'organization']` to `ROUTES`, before `['/', 'home']`.
- `AppHelper.jsx`: import `Organization` and add `['organization', <Organization />]` to `PAGES`.
- `HeaderHelper.jsx`: add `renderOrganizationDropdown()`.
  - It is a `NavDropdown` titled **Organization** (`id="organization-dropdown"`, `renderMenuOnMount`), with one item, **Settings** → `#/organization`.
  - Render it in `renderAuthLinks` for a logged-in user, for every user and not gated by `isAdmin`. Place it after the Admin Users link and before **My account**.
  - It stays hidden when logged out and while the session is pending, because `renderAuthLinks` already returns `null` while pending.
  - In its JSDoc, note that #43 and #44 will add their items to this dropdown.
- Update the JSDoc of `renderAuthLinks` / `HeaderHelper.render` that lists the logged-in links.

Specs:
- `HashRouteResolver` spec: `#/organization` resolves to `organization`.
- `AppHelper` spec: `organization` renders the `Organization` page (if the existing spec checks the page map).
- `HeaderHelper` / `Header` specs:
  - the Organization dropdown and its Settings link to `#/organization` are present for a logged-in non-admin and for an admin;
  - they are absent when logged out and while pending.

## Files to Change
- `frontend/assets/js/utils/routing/HashRouteResolver.js` — add the route.
- `frontend/assets/js/components/helpers/AppHelper.jsx` — register the page.
- `frontend/assets/js/components/common/header/helpers/HeaderHelper.jsx` — add the Organization dropdown.
- `frontend/specs/assets/js/utils/routing/` (`HashRouteResolver` spec) — new route case.
- `frontend/specs/assets/js/components/helpers/` (`AppHelper` spec, if present) — new page case.
- `frontend/specs/assets/js/components/common/header/` (helper and header specs) — dropdown cases.
