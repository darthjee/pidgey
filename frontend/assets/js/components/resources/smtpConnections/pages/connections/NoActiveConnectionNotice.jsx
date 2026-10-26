/**
 * Notice that sending is disabled because no SMTP connection is active.
 *
 * @description Rendered above the SMTP connections list, including when the list is empty.
 * @param {object} props - Component props.
 * @param {Array<{active: boolean}>} props.connections - The listed connections.
 * @returns {React.ReactElement|null} The notice, or `null` when a connection is active.
 */
export default function NoActiveConnectionNotice({ connections }) {
  if (connections.some((connection) => connection.active)) {
    return null;
  }

  return (
    <div className="alert alert-warning">
      No SMTP connection is active: sending email is disabled until one is activated.
    </div>
  );
}
