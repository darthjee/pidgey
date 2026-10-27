# Routes and header item
- `HashRouteResolver.js`: add `['/api-tokens/new', 'api-token-new']` and `['/api-tokens', 'api-tokens']`, the more specific path first, near the SMTP routes.
- `AppHelper.jsx`: import `ApiTokens` and `ApiTokenNew`, add the `'api-tokens'` and `'api-token-new'` PAGES entries.
- `HeaderHelper.jsx` `renderOrganizationDropdown`: add `<NavDropdown.Item href="#/api-tokens">API tokens</NavDropdown.Item>` after "SMTP connections", and update its JSDoc (no longer "will add").

## Files to Change
- `frontend/assets/js/utils/routing/HashRouteResolver.js` — two routes.
- `frontend/assets/js/components/helpers/AppHelper.jsx` — PAGES entries.
- `frontend/assets/js/components/common/header/helpers/HeaderHelper.jsx` — dropdown item.
- Matching specs under `frontend/specs/assets/js/` (route resolution, page rendering, dropdown item order).
