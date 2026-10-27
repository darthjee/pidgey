import Table from 'react-bootstrap/cjs/Table.js';
import ApiTokenRow from '../tokens/ApiTokenRow.jsx';

/**
 * Render the page body: a loading indicator while loading, the load error alert when the load
 * failed, otherwise the list.
 *
 * @param {object} state - Page state.
 * @param {object} handlers - Event handlers.
 * @returns {React.ReactElement} The rendered body.
 */
function renderBody(state, handlers) {
  if (state.loading) {
    return <div className="spinner-border" role="status" aria-label="Loading" />;
  }

  if (state.loadError) {
    return <div className="alert alert-danger">{state.loadError}</div>;
  }

  return (
    <>
      {renderListError(state)}
      {renderList(state, handlers)}
    </>
  );
}

/**
 * Render the error of the last row action, if any.
 *
 * @param {{listError: (string|null)}} state - Page state.
 * @returns {React.ReactElement|null} The error alert, or `null` when there is none.
 */
function renderListError(state) {
  if (!state.listError) {
    return null;
  }

  return <div className="alert alert-danger">{state.listError}</div>;
}

/**
 * Render the tokens table, in the API order, or an empty-state message when there is none.
 *
 * @param {{tokens: Array<object>}} state - Page state.
 * @param {{onRevoke: Function}} handlers - Event handlers.
 * @returns {React.ReactElement} The rendered table or empty-state message.
 */
function renderList(state, handlers) {
  if (state.tokens.length === 0) {
    return <p>No API tokens yet.</p>;
  }

  return (
    <Table striped bordered hover>
      <thead>
        <tr>
          <th>Name</th>
          <th>Prefix</th>
          <th>Scopes</th>
          <th>Created</th>
          <th>Last used</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        {state.tokens.map((token) => (
          <ApiTokenRow key={token.id} token={token} onRevoke={handlers.onRevoke(token)} />
        ))}
      </tbody>
    </Table>
  );
}

/**
 * Rendering helper for the API tokens list page.
 */
const ApiTokensHelper = {
  /**
   * Render the API tokens page: a heading with the New token button, then a loading
   * indicator, the load error, or the list (with the last row action's error).
   *
   * @param {{tokens: Array<object>, loading: boolean, loadError: (string|null),
   *   listError: (string|null)}} state - Page state.
   * @param {{onRevoke: Function}} handlers - Event handlers; `onRevoke` takes a token and
   *   returns that row's click handler.
   * @returns {React.ReactElement} The rendered API tokens page.
   */
  render(state, handlers) {
    return (
      <div className="container mt-4">
        <div className="d-flex justify-content-between align-items-center mb-3">
          <h1>API tokens</h1>
          <a href="#/api-tokens/new" className="btn btn-primary">New token</a>
        </div>
        {renderBody(state, handlers)}
      </div>
    );
  },
};

export default ApiTokensHelper;
