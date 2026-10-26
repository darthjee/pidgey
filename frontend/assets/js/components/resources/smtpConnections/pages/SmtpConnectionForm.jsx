import { useEffect, useMemo, useState } from 'react';
import SmtpConnectionFormController from './controllers/SmtpConnectionFormController.js';
import SmtpConnectionFormHelper from './helpers/SmtpConnectionFormHelper.jsx';

const INITIAL_FIELDS = {
  name: '', host: '', port: '', encryption: '', username: '', password: '', fromAddress: '',
};
const LOADING_STATE = { loading: true, loadError: null, notFound: false };
const LOADED_STATE = { loading: false, loadError: null, notFound: false };

/**
 * Build the mount-time load effect: on edit (an `id` is given), triggers `controller.load(id)`
 * once; on create, does nothing. Extracted as a plain function so it can be exercised
 * directly in tests without a React renderer.
 *
 * @param {SmtpConnectionFormController} controller - The page's controller.
 * @param {(number|string|undefined)} id - The connection id on edit.
 * @returns {Function} Effect callback.
 */
export function buildLoadEffect(controller, id) {
  return () => {
    if (id !== undefined) {
      controller.load(id);
    }
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
 * SMTP connection form page, shared by the create and edit routes: creates a connection when
 * no `id` is given, otherwise loads and edits that connection. The password is write-only: it
 * is never loaded, and the input is cleared after a successful save.
 *
 * @param {object} props - Component props.
 * @param {(number|string)} [props.id] - The connection id on edit; omitted on create.
 * @returns {React.ReactElement} The rendered form page.
 */
export default function SmtpConnectionForm({ id }) {
  const mode = id === undefined ? 'create' : 'edit';
  const [fields, setFields] = useState(INITIAL_FIELDS);
  const [stored, setStored] = useState(null);
  const [loadState, setLoadState] = useState(mode === 'edit' ? LOADING_STATE : LOADED_STATE);
  const [fieldErrors, setFieldErrors] = useState({});
  const [submitError, setSubmitError] = useState(null);
  const [success, setSuccess] = useState(false);

  const controller = useMemo(
    () => new SmtpConnectionFormController({
      setFields, setStored, setLoadState, setFieldErrors, setSubmitError, setSuccess,
    }),
    [],
  );

  useEffect(() => buildLoadEffect(controller, id)(), [controller, id]);

  const onSubmit = (event) => {
    event.preventDefault();
    return controller.handleSubmit(id, fields, stored);
  };

  return SmtpConnectionFormHelper.render(
    {
      mode, ...fields, stored, ...loadState, fieldErrors, submitError, success,
    },
    {
      onSubmit,
      onChange: (field) => (event) => setFields(buildFieldUpdate(field, event.target.value)),
      onRemoveCredentials: () => controller.handleRemoveCredentials(id),
    },
  );
}
