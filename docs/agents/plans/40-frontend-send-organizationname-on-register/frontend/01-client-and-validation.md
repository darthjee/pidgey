# Client and validation
Send `organizationName` from the client and add the matching client-side validation.

- `AccountsClient.register`:
  - destructure `organizationName` and add it to the posted body as `organizationName` (camelCase, as the API expects);
  - update the JSDoc `@param` type.
- `fieldValidators.js`:
  - add `MAX_ORGANIZATION_NAME_LENGTH = 255`;
  - add an exported `validateOrganizationName(name, field = 'organizationName')` that returns an error map, following the style of `validateEmail` / `validatePasswordLength`;
  - error when `name` is missing or `name.trim()` is empty: `Organization name is required`;
  - error when `name.trim().length > 255`: `Organization name must be at most 255 characters`.
- `formValidators.js`: `validateRegistration` destructures `organizationName` and spreads `validateOrganizationName(organizationName)`. Update the JSDoc.

Specs:
- `AccountsClientSpec`: register posts `organizationName` alongside the existing fields.
- `fieldValidatorsSpec`: empty, `undefined`, whitespace-only and 256-character (after trim) values fail; 1- and 255-character values pass; padded input such as `"  a  "` passes.
- `formValidatorsSpec`: `validateRegistration` reports `organizationName` together with the other field errors, and is empty for a fully valid form.

## Files to Change
- `frontend/assets/js/client/AccountsClient.js` — send `organizationName`.
- `frontend/assets/js/utils/validation/fieldValidators.js` — new validator and constant.
- `frontend/assets/js/utils/validation/formValidators.js` — include it in `validateRegistration`.
- `frontend/specs/assets/js/client/AccountsClientSpec.js`
- `frontend/specs/assets/js/utils/validation/fieldValidatorsSpec.js`
- `frontend/specs/assets/js/utils/validation/formValidatorsSpec.js`
