# Organization screen
Add the page under `frontend/assets/js/components/resources/organization/pages/`, following the `AuthorizationRequests` pattern: a page component, a controller and a helper.

**`controllers/OrganizationController.js`**
- Constructor: `(setFields, setFieldErrors, setLoadState, setSubmitError, setSuccess, client = OrganizationClient)`.
  - `setFields` holds `{ name }`.
  - `setLoadState` holds `'loading' | 'loaded' | 'error'`, or a `loading` flag plus a `loadError`, whichever reads cleaner. The setters are injected for spec testability.
- `load()`: calls `client.get()`.
  - On a result, sets `{ name: organization.name }` and the loaded state.
  - On `undefined` (the session ended and the login modal is open), leaves the screen idle and not erroring.
  - On a thrown error, sets the load error message.
- `validate({ name })`: after trimming, an empty name → `name: 'Organization name is required'`, and over 255 characters → `name: 'Organization name must be at most 255 characters'`. Put the rule in `utils/validation/fieldValidators.js` if it fits the existing validators' shape; otherwise keep it in the controller.
- `handleSubmit(fields)`:
  - Clear `success` and errors, and validate. On errors, set the field errors and make no API call.
  - Otherwise call `client.update({ name: fields.name.trim() })`.
  - On a result, set `{ name: organization.name }` and `success = true`. On `undefined`, do nothing.
  - On an `ApiError` with code `VALIDATION_FAILED`, set `fieldErrors.name` to the joined `details` (falling back to `error.message`). Any other error becomes the submit error message.

**`helpers/OrganizationHelper.jsx`** (object module): `render(state, handlers)` renders:
- a heading;
- a loading indicator while loading;
- an alert with the load error, if any;
- otherwise a form with the **Organization name** text field (`FormFieldsHelper.renderField`), the submit error and success alerts (`FormFieldsHelper`), and a **Save** button.

**`Organization.jsx`**: holds the state with `useState`, builds the controller once with `useMemo`, and runs `controller.load()` on mount through an exported `buildLoadEffect(controller)`, like `AuthorizationRequests.jsx`. It wires `onChange` and `onSubmit` (with `preventDefault`) and renders through the helper. No client-side login or admin check.

Specs, mirroring the existing page / controller / helper specs (`MyAccountSpec.js`, `AuthorizationRequests*Spec.js`; helpers `renderedOutput.js`, `renderCapturingHandlers.js`, `flushMicrotasks.js`):
- **Controller:**
  - load success, load `undefined`, load failure;
  - validation: empty, whitespace-only, 256 characters (rejected) and 255 characters (accepted);
  - the submit payload is trimmed;
  - save success puts the returned name back;
  - save `undefined`;
  - `VALIDATION_FAILED` details go on the name field;
  - any other `ApiError` becomes the submit error.
- **Helper:** loading, load error, the form with the field error, the submit error and the success alerts.
- **Page:** `buildLoadEffect` calls `load`; the handlers delegate to the controller.

## Files to Change
- `frontend/assets/js/components/resources/organization/pages/Organization.jsx` — new page.
- `frontend/assets/js/components/resources/organization/pages/controllers/OrganizationController.js` — new controller.
- `frontend/assets/js/components/resources/organization/pages/helpers/OrganizationHelper.jsx` — new helper.
- `frontend/assets/js/utils/validation/fieldValidators.js` — optional: the organization-name rule.
- `frontend/specs/assets/js/components/resources/organization/pages/OrganizationSpec.js` — new specs.
- `frontend/specs/assets/js/components/resources/organization/pages/controllers/OrganizationControllerSpec.js` — new specs.
- `frontend/specs/assets/js/components/resources/organization/pages/helpers/OrganizationHelperSpec.js` — new specs.
