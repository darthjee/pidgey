/**
 * Activate button for a row of the SMTP connections table.
 *
 * @description Hidden on the active connection, which is already the one used to send.
 * @param {object} props - Component props.
 * @param {{active: boolean}} props.connection - The row's connection.
 * @param {Function} props.onActivate - Click handler, already bound to the row's connection.
 * @returns {React.ReactElement|null} The Activate button, or `null` on the active connection.
 */
export default function ActivateConnectionButton({ connection, onActivate }) {
  if (connection.active) {
    return null;
  }

  return (
    <button type="button" className="btn btn-sm btn-success me-2" onClick={onActivate}>
      Activate
    </button>
  );
}
