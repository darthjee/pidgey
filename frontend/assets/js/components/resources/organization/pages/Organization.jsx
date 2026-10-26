import { useEffect, useMemo, useState } from 'react';
import OrganizationController from './controllers/OrganizationController.js';
import OrganizationHelper from './helpers/OrganizationHelper.jsx';

const INITIAL_FIELDS = { name: '' };
const INITIAL_LOAD_STATE = { loading: true, loadError: null };

/**
 * Build the mount-time load effect: triggers `controller.load()` once, unconditionally — no
 * session/login check of any kind, since a logged-out `401` is handled entirely by
 * `ApiClient`'s refresh/login-modal flow. Extracted as a plain function so it can be exercised
 * directly in tests without a React renderer.
 *
 * @param {OrganizationController} controller - The page's controller.
 * @returns {Function} Effect callback.
 */
export function buildLoadEffect(controller) {
  return () => {
    controller.load();
  };
}

/**
 * Build the state updater applying one field change to the form fields. Extracted as a plain
 * function so it can be exercised directly in tests without a React renderer.
 *
 * @param {string} field - The changed field's name.
 * @param {string} value - The field's new value.
 * @returns {Function} State updater returning the fields with `field` set to `value`.
 */
export function buildFieldUpdate(field, value) {
  return (current) => ({ ...current, [field]: value });
}

/**
 * Organization page: shows the caller's own organization and lets them rename it. Open to any
 * logged-in, approved user — there is no client-side login or admin check; a `401` is handled
 * by `ApiClient`.
 *
 * @returns {React.ReactElement} The rendered Organization page.
 */
export default function Organization() {
  const [fields, setFields] = useState(INITIAL_FIELDS);
  const [fieldErrors, setFieldErrors] = useState({});
  const [loadState, setLoadState] = useState(INITIAL_LOAD_STATE);
  const [submitError, setSubmitError] = useState(null);
  const [success, setSuccess] = useState(false);

  const controller = useMemo(
    () => new OrganizationController(setFields, setFieldErrors, setLoadState, setSubmitError, setSuccess),
    [],
  );

  useEffect(() => buildLoadEffect(controller)(), [controller]);

  const onChange = (field) => (event) => {
    setFields(buildFieldUpdate(field, event.target.value));
  };

  const onSubmit = (event) => {
    event.preventDefault();
    return controller.handleSubmit(fields);
  };

  return OrganizationHelper.render(
    {
      ...fields, ...loadState, fieldErrors, submitError, success,
    },
    { onSubmit, onChange },
  );
}
