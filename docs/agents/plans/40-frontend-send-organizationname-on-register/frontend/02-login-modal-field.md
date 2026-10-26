# Login modal field
Add the Organization name field to the modal's Register mode and wire its state.

- `LoginModalFormsHelper.jsx`:
  - in `FIELDS_BY_MODE`, insert `['organizationName', 'text', 'Organization name']` in the `register` entry, after `email` and before `password`;
  - add `['organizationName', handlers.onOrganizationNameChange]` to `onChangeByField` in `renderForm`;
  - update the JSDoc state/handler shapes.
- `LoginModal.jsx`:
  - add `organizationName: ''` to `INITIAL_FIELDS`;
  - add `onOrganizationNameChange: handleFieldChange('organizationName')` to the handlers.
- `LoginModalHelper.jsx`: update the JSDoc state/handler shapes.
- `LoginModalController.js`: `#submitRegister` already passes `fields` to `validateRegistration` and `client.register`, so no logic changes. Update the JSDoc field shape and remove the "same shape as today's `RegisterController`" wording.
- Check that switching modes or resetting the modal clears `organizationName` like the other fields (it does if the reset uses `INITIAL_FIELDS`).

Specs:
- `LoginModalFormsHelperSpec`: Register mode renders the Organization name input (id prefix `login-modal-`); other modes do not.
- `LoginModalControllerSpec`, Register mode:
  - a missing or blank organization name sets an `organizationName` field error and does not call `client.register`;
  - a valid form calls `client.register` with `organizationName`;
  - an `ApiError` sets the submit error to its message.
- `LoginModalSpec` / `useLoginModalSpec`: adjust any expectation of the initial field set or handler names.

## Files to Change
- `frontend/assets/js/components/common/loginModal/helpers/LoginModalFormsHelper.jsx`
- `frontend/assets/js/components/common/loginModal/LoginModal.jsx`
- `frontend/assets/js/components/common/loginModal/helpers/LoginModalHelper.jsx` — JSDoc only.
- `frontend/assets/js/components/common/loginModal/controllers/LoginModalController.js` — JSDoc only.
- `frontend/specs/assets/js/components/common/loginModal/helpers/LoginModalFormsHelperSpec.js`
- `frontend/specs/assets/js/components/common/loginModal/controllers/LoginModalControllerSpec.js`
- `frontend/specs/assets/js/components/common/loginModal/LoginModalSpec.js` and `hooks/useLoginModalSpec.js` — only if they assert the field set.
