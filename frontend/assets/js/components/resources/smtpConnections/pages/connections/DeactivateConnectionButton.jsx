/**
 * Deactivate button for a row of the SMTP connections table.
 *
 * @description Shown only on the active connection, the one used to send.
 * @param {object} props - Component props.
 * @param {{active: boolean}} props.connection - The row's connection.
 * @param {Function} props.onDeactivate - Click handler, already bound to the row's connection.
 * @returns {React.ReactElement|null} The Deactivate button, or `null` on an inactive
 *   connection.
 */
export default function DeactivateConnectionButton({ connection, onDeactivate }) {
  if (!connection.active) {
    return null;
  }

  return (
    <button type="button" className="btn btn-sm btn-warning me-2" onClick={onDeactivate}>
      Deactivate
    </button>
  );
}
