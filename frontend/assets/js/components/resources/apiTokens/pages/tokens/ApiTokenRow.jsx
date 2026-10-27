import Badge from 'react-bootstrap/cjs/Badge.js';

/**
 * Format an ISO 8601 timestamp for display in the user's locale.
 *
 * @param {string} value - The ISO 8601 timestamp.
 * @returns {string} The localized date and time.
 */
function formatDate(value) {
  return new Date(value).toLocaleString();
}

/**
 * Render the status / action cell: the Revoked badge with the revocation date on a revoked
 * token, otherwise the Revoke button.
 *
 * @param {{revokedAt: (string|null)}} token - The row's token.
 * @param {Function} onRevoke - Click handler, already bound to the row's token.
 * @returns {React.ReactElement} The cell content.
 */
function renderStatus(token, onRevoke) {
  if (token.revokedAt) {
    return (
      <>
        <Badge bg="secondary" className="me-2">Revoked</Badge>
        <span>{formatDate(token.revokedAt)}</span>
      </>
    );
  }

  return (
    <button type="button" className="btn btn-sm btn-danger" onClick={onRevoke}>
      Revoke
    </button>
  );
}

/**
 * One row of the API tokens table.
 *
 * @description Shows the token's name, display prefix, scopes, creation and last-use dates
 * ("Never" when it was never used), and either the Revoked badge (revoked token) or the Revoke
 * button (active token).
 * @param {object} props - Component props.
 * @param {object} props.token - The row's API token item.
 * @param {Function} props.onRevoke - Click handler, already bound to the row's token.
 * @returns {React.ReactElement} The rendered row.
 */
export default function ApiTokenRow({ token, onRevoke }) {
  return (
    <tr>
      <td>{token.name}</td>
      <td><code>{token.tokenPrefix}</code>…</td>
      <td>{token.scopes.join(', ')}</td>
      <td>{formatDate(token.createdAt)}</td>
      <td>{token.lastUsedAt === null ? 'Never' : formatDate(token.lastUsedAt)}</td>
      <td>{renderStatus(token, onRevoke)}</td>
    </tr>
  );
}
