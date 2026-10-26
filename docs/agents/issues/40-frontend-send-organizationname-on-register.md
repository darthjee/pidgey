# Frontend: send organizationName on register

## Description

Registration from the admin UI is broken: the frontend never sends `organizationName`, which `POST /auth/register.json` requires. This issue adds a required "Organization name" field to the live register form, sends it, and removes the orphaned `RegisterController`. The change is frontend-only; the API specs under `docs/agents/specs/api/**` are the contract and stay unchanged.

## Problem

- `POST /auth/register.json` requires `organizationName`: a string, not empty after trimming, at most 255 characters (`docs/agents/specs/api/endpoints/auth/session.md`). Register creates the organization and its first user in one transaction.
- `AccountsClient.register` (`frontend/assets/js/client/AccountsClient.js`) sends only `username`, `email`, `password` and `password_confirmation`. Every registration therefore answers `400 VALIDATION_FAILED`.
- The only live registration form is the **Register** mode of the login modal:
  - fields in `FIELDS_BY_MODE` in `frontend/assets/js/components/common/loginModal/helpers/LoginModalFormsHelper.jsx`;
  - state in `LoginModal.jsx`;
  - submit in `LoginModalController#submitRegister`.
- `frontend/assets/js/components/resources/accounts/pages/controllers/RegisterController.js` is not used by any page or route. Only its spec references it.

Found while checking frontend/API compatibility in #32 (epic #24).

## Expected Behavior

- The login modal's Register mode shows a required **Organization name** field (input type `text`), placed after Email and before Password.
- Client-side validation mirrors the API rule: the field is required (not empty after trimming) and at most 255 characters. Errors appear inline on the field, like the other register fields, and block the API call.
- `AccountsClient.register` sends `organizationName` in the request body.
- A `400` (or any other error) from register keeps today's behavior: `error.message` is shown as the form's submit error. No mapping of `error.details` to fields.
- `RegisterController.js` and `RegisterControllerSpec.js` are deleted.

## Solution

### Changes

- `AccountsClient.register`:
  - accept `organizationName` and send it as `organizationName`;
  - update the JSDoc.
- `fieldValidators.js` / `formValidators.js`:
  - add an organization-name validator (required after trim, max 255 characters);
  - include it in `validateRegistration`, so the modal gets it automatically.
- Login modal:
  - add `['organizationName', 'text', 'Organization name']` to the `register` entry of `FIELDS_BY_MODE`;
  - add `organizationName: ''` to the modal's initial form state;
  - add an `onOrganizationNameChange` handler and wire it in the field/handler table;
  - update the JSDoc of the helpers.
- Delete `RegisterController.js` and its spec. Remove stale references to it in comments (e.g. the `#submitRegister` JSDoc says "same shape as today's `RegisterController`").

### Decisions

- **Dead code:** `RegisterController` is deleted rather than updated, since no page uses it.
- **API errors:** shown as the submit error, as today. The API returns validation messages as flat strings, not keyed by field, and the client-side checks mirror the API rules, so a `400` should be rare.
- **Trimming:** the value is sent as typed. The API trims it; the client check uses the trimmed length.
- **`password_confirmation`:** still sent. The API drops it as an unknown field (`conventions/validation.md`), and this issue does not change that.

### Testing

Jasmine specs:

- `AccountsClient.register` posts `organizationName` (alongside the existing fields) to `/auth/register.json`.
- The organization-name validator: empty, whitespace-only and 256-character values fail; 1- and 255-character values pass.
- `validateRegistration` reports `organizationName` errors together with the other field errors.
- `LoginModalController` Register mode: a missing organization name sets a field error and does not call the client; a valid form passes `organizationName` to `client.register`.
- The modal helper renders the Organization name field in Register mode only.

ESLint stays clean.

### Out of scope

- Showing the organization anywhere else (#42, #45).
- Mapping `error.details` to individual fields.
- The single-flight refresh (#41) and the Vite `*.json` proxy (#46).

## Benefits

- Registration from the admin UI works again.
- The register form matches the API contract, and the client-side validation matches the API rules.
- Dead code is removed, so there is only one registration path to maintain.
