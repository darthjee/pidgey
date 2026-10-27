import FormFieldsHelper from '../../../../common/forms/helpers/FormFieldsHelper.jsx';
import { API_TOKEN_SCOPES } from '../controllers/ApiTokenFormRules.js';
import ApiTokenCreatedPanel from '../tokens/ApiTokenCreatedPanel.jsx';

const ID_PREFIX = 'api-token-';

/**
 * Render the page body: the one-time token panel once a token was created, otherwise the
 * form.
 *
 * @param {object} state - Page state.
 * @param {object} handlers - Event handlers.
 * @returns {React.ReactElement} The rendered body.
 */
function renderBody(state, handlers) {
  if (state.createdToken) {
    return <ApiTokenCreatedPanel token={state.createdToken} copyState={state.copyState} onCopy={handlers.onCopy} />;
  }

  return renderForm(state, handlers);
}

/**
 * Render the scopes field error, if any.
 *
 * @param {(string|undefined)} error - The scopes error message.
 * @returns {React.ReactElement|null} The feedback, or `null` when there is none.
 */
function renderScopesFeedback(error) {
  if (!error) {
    return null;
  }

  return <div className="invalid-feedback d-block">{error}</div>;
}

/**
 * Render one checkbox per known scope, with the scopes error.
 *
 * @param {{scopes: string[], fieldErrors: object}} state - Page state.
 * @param {Function} onToggleScope - Called with a scope name; returns its change handler.
 * @returns {React.ReactElement} The rendered fieldset.
 */
function renderScopesField(state, onToggleScope) {
  const error = state.fieldErrors.scopes;

  return (
    <fieldset className="mb-3">
      <legend className="form-label fs-6">Scopes</legend>
      {API_TOKEN_SCOPES.map((scope) => (
        <div className="form-check" key={scope}>
          <input
            id={`${ID_PREFIX}scope-${scope}`}
            type="checkbox"
            className={`form-check-input${error ? ' is-invalid' : ''}`}
            checked={state.scopes.includes(scope)}
            onChange={onToggleScope(scope)}
          />
          <label className="form-check-label" htmlFor={`${ID_PREFIX}scope-${scope}`}>{scope}</label>
        </div>
      ))}
      {renderScopesFeedback(error)}
    </fieldset>
  );
}

/**
 * Render the form: the submit error, the Name field, the Scopes checkboxes and the buttons.
 *
 * @param {object} state - Page state.
 * @param {object} handlers - Event handlers.
 * @returns {React.ReactElement} The rendered form.
 */
function renderForm(state, handlers) {
  return (
    <form onSubmit={handlers.onSubmit} noValidate>
      {FormFieldsHelper.renderSubmitError(state)}
      {FormFieldsHelper.renderField('name', 'text', 'Name', state, handlers.onNameChange, ID_PREFIX)}
      {renderScopesField(state, handlers.onToggleScope)}
      <button type="submit" className="btn btn-primary" disabled={state.submitting}>Create</button>
      <a href="#/api-tokens" className="btn btn-secondary ms-2">Cancel</a>
    </form>
  );
}

/**
 * Rendering helper for the API token create page.
 */
const ApiTokenNewHelper = {
  /**
   * Render the create page: the form, or the one-time token panel once a token was created.
   *
   * @param {{name: string, scopes: string[], fieldErrors: object, submitError: (string|null),
   *   submitting: boolean, createdToken: (string|null), copyState: string}} state - Page
   *   state.
   * @param {{onSubmit: Function, onNameChange: Function, onToggleScope: Function,
   *   onCopy: Function}} handlers - Event handlers; `onToggleScope` is called with a scope
   *   name and returns that checkbox's change handler.
   * @returns {React.ReactElement} The rendered page.
   */
  render(state, handlers) {
    return (
      <div className="container mt-4">
        <h1>New API token</h1>
        {renderBody(state, handlers)}
      </div>
    );
  },
};

export default ApiTokenNewHelper;
