import Table from 'react-bootstrap/cjs/Table.js';
import ActivateConnectionButton from '../connections/ActivateConnectionButton.jsx';
import NoActiveConnectionNotice from '../connections/NoActiveConnectionNotice.jsx';
import SmtpConnectionTestModal from '../connections/SmtpConnectionTestModal.jsx';

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
      <NoActiveConnectionNotice connections={state.connections} />
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
 * Render the connections table, or an empty-state message when there is none.
 *
 * @param {{connections: Array<object>}} state - Page state.
 * @param {object} handlers - Event handlers.
 * @returns {React.ReactElement} The rendered table or empty-state message.
 */
function renderList(state, handlers) {
  if (state.connections.length === 0) {
    return <p>No SMTP connections yet. Create one to start sending email.</p>;
  }

  return (
    <Table striped bordered hover>
      <thead>
        <tr>
          <th>Name</th>
          <th>Server</th>
          <th>Encryption</th>
          <th>From address</th>
          <th>Credentials</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        {state.connections.map((connection) => renderRow(connection, handlers))}
      </tbody>
    </Table>
  );
}

/**
 * Render the Active badge of the active connection.
 *
 * @param {{active: boolean}} connection - The row's connection.
 * @returns {React.ReactElement|null} The badge, or `null` on an inactive connection.
 */
function renderActiveBadge(connection) {
  if (!connection.active) {
    return null;
  }

  return <span className="badge bg-success ms-2">Active</span>;
}

/**
 * Tell whether a connection has credentials stored.
 *
 * @param {{username: (string|null), hasPassword: boolean}} connection - The connection.
 * @returns {string} `Set` when a username or a password is stored, otherwise `None`.
 */
function credentialsLabel(connection) {
  return connection.username !== null || connection.hasPassword ? 'Set' : 'None';
}

/**
 * Render a single connection row: its name (linking to its edit form) with the Active badge,
 * its settings and its Activate / Send test email / Delete actions.
 *
 * @param {object} connection - The row's connection.
 * @param {object} handlers - Event handlers.
 * @returns {React.ReactElement} The rendered row.
 */
function renderRow(connection, handlers) {
  return (
    <tr key={connection.id}>
      <td>
        <a href={`#/smtp-connections/${connection.id}/edit`}>{connection.name}</a>
        {renderActiveBadge(connection)}
      </td>
      <td>{`${connection.host}:${connection.port}`}</td>
      <td>{connection.encryption}</td>
      <td>{connection.fromAddress}</td>
      <td>{credentialsLabel(connection)}</td>
      <td>
        <ActivateConnectionButton connection={connection} onActivate={handlers.onActivate(connection.id)} />
        <button type="button" className="btn btn-sm btn-secondary me-2" onClick={handlers.onOpenTest(connection)}>
          Send test email
        </button>
        <button type="button" className="btn btn-sm btn-danger me-2" onClick={handlers.onDelete(connection)}>
          Delete
        </button>
      </td>
    </tr>
  );
}

/**
 * Rendering helper for the SMTP connections list page.
 */
const SmtpConnectionsHelper = {
  /**
   * Render the SMTP connections page: a heading with the New connection button, then a
   * loading indicator, the load error, or the list (with the no-active notice and the last
   * row action's error), plus the test-email modal.
   *
   * @param {{connections: Array<object>, loading: boolean, loadError: (string|null),
   *   listError: (string|null), test: object}} state - Page state; `test` is the test-email
   *   modal state.
   * @param {{onActivate: Function, onDelete: Function, onOpenTest: Function,
   *   onCloseTest: Function, onRecipientChange: Function, onSubmitTest: Function}} handlers -
   *   Event handlers; `onActivate` takes a connection id, `onDelete` / `onOpenTest` a
   *   connection, and each returns that row's click handler.
   * @returns {React.ReactElement} The rendered SMTP connections page.
   */
  render(state, handlers) {
    return (
      <div className="container mt-4">
        <div className="d-flex justify-content-between align-items-center mb-3">
          <h1>SMTP connections</h1>
          <a href="#/smtp-connections/new" className="btn btn-primary">New connection</a>
        </div>
        {renderBody(state, handlers)}
        <SmtpConnectionTestModal
          state={state.test}
          onClose={handlers.onCloseTest}
          onRecipientChange={handlers.onRecipientChange}
          onSubmit={handlers.onSubmitTest}
        />
      </div>
    );
  },
};

export default SmtpConnectionsHelper;
