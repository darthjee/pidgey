/**
 * Remove credentials button of the SMTP connection edit form: clears both the stored
 * username and password.
 *
 * @description Shown only on edit, when credentials are stored (a username or a password).
 * @param {object} props - Component props.
 * @param {({username: (string|null), hasPassword: boolean}|null)} props.stored - The stored
 *   credentials; `null` on create.
 * @param {Function} props.onRemoveCredentials - Click handler.
 * @returns {React.ReactElement|null} The button, or `null` when there is nothing to remove.
 */
export default function RemoveCredentialsButton({ stored, onRemoveCredentials }) {
  if (!stored || (stored.username === null && !stored.hasPassword)) {
    return null;
  }

  return (
    <button type="button" className="btn btn-outline-danger ms-2" onClick={onRemoveCredentials}>
      Remove credentials
    </button>
  );
}
