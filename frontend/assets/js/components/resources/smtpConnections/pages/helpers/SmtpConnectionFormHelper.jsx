import FormFieldsHelper from '../../../../common/forms/helpers/FormFieldsHelper.jsx';
import RemoveCredentialsButton from '../connections/RemoveCredentialsButton.jsx';
import { ENCRYPTIONS } from '../controllers/SmtpConnectionFormRules.js';

const ID_PREFIX = 'smtp-connection-';
const LIST_HREF = '#/smtp-connections';

const TITLES = new Map([
  ['create', 'New SMTP connection'],
  ['edit', 'Edit SMTP connection'],
]);

/**
 * Render the page body: a loading indicator, the not-found message, the load error, or the
 * form.
 *
 * @param {object} state - Page state.
 * @param {object} handlers - Event handlers.
 * @returns {React.ReactElement} The rendered body.
 */
function renderBody(state, handlers) {
  if (state.loading) {
    return <div className="spinner-border" role="status" aria-label="Loading" />;
  }

  if (state.notFound) {
    return (
      <div className="alert alert-warning">
        SMTP connection not found. <a href={LIST_HREF}>Back to SMTP connections</a>
      </div>
    );
  }

  if (state.loadError) {
    return <div className="alert alert-danger">{state.loadError}</div>;
  }

  return renderForm(state, handlers);
}

/**
 * Render the notice that a new connection starts inactive (create only).
 *
 * @param {{mode: string}} state - Page state.
 * @returns {React.ReactElement|null} The notice, or `null` on edit.
 */
function renderInactiveNotice(state) {
  if (state.mode !== 'create') {
    return null;
  }

  return (
    <div className="alert alert-info">
      New connections are created inactive; activate one from the SMTP connections list.
    </div>
  );
}

/**
 * Render a field's validation error, if any.
 *
 * @param {(string|undefined)} error - The field's error message.
 * @returns {React.ReactElement|null} The feedback, or `null` when there is none.
 */
function renderFeedback(error) {
  if (!error) {
    return null;
  }

  return <div className="invalid-feedback">{error}</div>;
}

/**
 * Build the class of a form control, flagging it as invalid when it has an error.
 *
 * @param {string} base - The base class.
 * @param {(string|undefined)} error - The field's error message.
 * @returns {string} The class name.
 */
function controlClass(base, error) {
  return error ? `${base} is-invalid` : base;
}

/**
 * Render the encryption select.
 *
 * @param {object} state - Page state.
 * @param {Function} onChange - Change handler.
 * @returns {React.ReactElement} The rendered field.
 */
function renderEncryptionField(state, onChange) {
  const error = state.fieldErrors.encryption;

  return (
    <div className="mb-3">
      <label className="form-label" htmlFor={`${ID_PREFIX}encryption`}>Encryption</label>
      <select
        id={`${ID_PREFIX}encryption`}
        className={controlClass('form-select', error)}
        value={state.encryption}
        onChange={onChange}
      >
        <option value="">Select…</option>
        {ENCRYPTIONS.map((encryption) => <option key={encryption} value={encryption}>{encryption}</option>)}
      </select>
      {renderFeedback(error)}
    </div>
  );
}

/**
 * Render whether a password is stored (edit only).
 *
 * @param {({hasPassword: boolean}|null)} stored - The stored credentials.
 * @returns {React.ReactElement|null} The status, or `null` on create.
 */
function renderPasswordStatus(stored) {
  if (!stored) {
    return null;
  }

  return (
    <div className="form-text">
      {stored.hasPassword ? 'Password: set. Leave empty to keep it.' : 'Password: not set.'}
    </div>
  );
}

/**
 * Render the write-only password input, with the stored-password status on edit.
 *
 * @param {object} state - Page state.
 * @param {Function} onChange - Change handler.
 * @returns {React.ReactElement} The rendered field.
 */
function renderPasswordField(state, onChange) {
  const error = state.fieldErrors.password;

  return (
    <div className="mb-3">
      <label className="form-label" htmlFor={`${ID_PREFIX}password`}>Password</label>
      <input
        id={`${ID_PREFIX}password`}
        type="password"
        autoComplete="new-password"
        className={controlClass('form-control', error)}
        value={state.password}
        onChange={onChange}
      />
      {renderFeedback(error)}
      {renderPasswordStatus(state.stored)}
    </div>
  );
}

/**
 * Render the form: outcome alerts, the fields and the buttons.
 *
 * @param {object} state - Page state.
 * @param {object} handlers - Event handlers.
 * @returns {React.ReactElement} The rendered form.
 */
function renderForm(state, handlers) {
  const field = (name, type, label) => FormFieldsHelper.renderField(
    name, type, label, state, handlers.onChange(name), ID_PREFIX,
  );

  return (
    <form onSubmit={handlers.onSubmit} noValidate>
      {renderInactiveNotice(state)}
      {FormFieldsHelper.renderSubmitError(state)}
      {FormFieldsHelper.renderSuccess(state, 'SMTP connection saved.')}
      {field('name', 'text', 'Name')}
      {field('host', 'text', 'Host')}
      {field('port', 'number', 'Port')}
      {renderEncryptionField(state, handlers.onChange('encryption'))}
      {field('username', 'text', 'Username')}
      {renderPasswordField(state, handlers.onChange('password'))}
      {field('fromAddress', 'email', 'From address')}
      <button type="submit" className="btn btn-primary">Save</button>
      <a href={LIST_HREF} className="btn btn-secondary ms-2">Cancel</a>
      <RemoveCredentialsButton stored={state.stored} onRemoveCredentials={handlers.onRemoveCredentials} />
    </form>
  );
}

/**
 * Rendering helper for the SMTP connection create / edit form page.
 */
const SmtpConnectionFormHelper = {
  /**
   * Render the form page: a heading per mode, then a loading indicator, the not-found
   * message, the load error, or the form.
   *
   * @param {{mode: ('create'|'edit'), name: string, host: string, port: string,
   *   encryption: string, username: string, password: string, fromAddress: string,
   *   stored: (object|null), loading: boolean, loadError: (string|null), notFound: boolean,
   *   fieldErrors: object, submitError: (string|null), success: boolean}} state - Page state.
   * @param {{onSubmit: Function, onChange: Function, onRemoveCredentials: Function}}
   *   handlers - Event handlers; `onChange` is called with the field name and returns that
   *   field's change handler.
   * @returns {React.ReactElement} The rendered page.
   */
  render(state, handlers) {
    return (
      <div className="container mt-4">
        <h1>{TITLES.get(state.mode)}</h1>
        {renderBody(state, handlers)}
      </div>
    );
  },
};

export default SmtpConnectionFormHelper;
