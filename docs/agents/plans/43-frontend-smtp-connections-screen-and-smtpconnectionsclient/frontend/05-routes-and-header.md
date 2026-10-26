# Routes, pages and header item
- `HashRouteResolver.js` `ROUTES`: add, before `['/organization', 'organization']` and in this
  order, `['/smtp-connections/new', 'smtp-connection-new']`,
  `['/smtp-connections/:id/edit', 'smtp-connection-edit']`,
  `['/smtp-connections', 'smtp-connections']` (specific before generic).
- `AppHelper.jsx` `PAGES`: map the three keys to the pages from steps 03 and 04.
- `HeaderHelper.jsx` `renderOrganizationDropdown`: add
  `<NavDropdown.Item href="#/smtp-connections">SMTP connections</NavDropdown.Item>` after
  **Settings**.
- Specs: the resolver resolves the three hashes to their keys (including an id), `AppHelper`
  renders the right page per key, and the header's Organization dropdown has the new item
  for logged-in, approved users only (not when logged out or pending).

## Files to Change
- `frontend/assets/js/utils/routing/HashRouteResolver.js` — three routes.
- `frontend/assets/js/components/helpers/AppHelper.jsx` — three pages.
- `frontend/assets/js/components/common/header/helpers/HeaderHelper.jsx` — dropdown item.
- `frontend/specs/assets/js/utils/routing/HashRouteResolverSpec.js`,
  `frontend/specs/assets/js/components/helpers/AppHelperSpec.js`,
  `frontend/specs/assets/js/components/common/header/helpers/HeaderHelperSpec.js` — specs.
