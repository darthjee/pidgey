import FormFieldsHelper from '../../../../common/forms/helpers/FormFieldsHelper.jsx';

const ID_PREFIX = 'organization-';

/**
 * Render the page body: a loading indicator while loading, the load error alert when the load
 * failed, otherwise the rename form.
 *
 * @param {object} state - Page state.
 * @param {{onSubmit: Function, onChange: Function}} handlers - Event handlers.
 * @returns {React.ReactElement} The rendered body.
 */
function renderBody(state, handlers) {
  if (state.loading) {
    return <div className="spinner-border" role="status" aria-label="Loading" />;
  }

  if (state.loadError) {
    return <div className="alert alert-danger">{state.loadError}</div>;
  }

  return renderForm(state, handlers);
}

/**
 * Render the rename form: the organization name field, the submit error and success alerts,
 * and the Save button.
 *
 * @param {object} state - Page state.
 * @param {{onSubmit: Function, onChange: Function}} handlers - Event handlers.
 * @returns {React.ReactElement} The rendered form.
 */
function renderForm(state, handlers) {
  return (
    <form onSubmit={handlers.onSubmit} noValidate>
      {FormFieldsHelper.renderSubmitError(state)}
      {FormFieldsHelper.renderSuccess(state, 'Organization updated.')}
      {FormFieldsHelper.renderField(
        'name', 'text', 'Organization name', state, handlers.onChange('name'), ID_PREFIX,
      )}
      <button type="submit" className="btn btn-primary">Save</button>
    </form>
  );
}

/**
 * Rendering helper for the Organization page.
 */
const OrganizationHelper = {
  /**
   * Render the Organization page: a heading plus a loading indicator, the load error, or the
   * rename form.
   *
   * @param {{name: string, fieldErrors: object, loading: boolean, loadError: (string|null),
   *   submitError: (string|null), success: boolean}} state - Page state.
   * @param {{onSubmit: Function, onChange: Function}} handlers - Event handlers; `onChange` is
   *   called with the field name and returns that field's change handler.
   * @returns {React.ReactElement} The rendered Organization page.
   */
  render(state, handlers) {
    return (
      <div className="container mt-4">
        <h1>Organization</h1>
        {renderBody(state, handlers)}
      </div>
    );
  },
};

export default OrganizationHelper;
