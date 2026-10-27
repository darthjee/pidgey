import { COPY_DONE, COPY_FAILED } from '../controllers/ApiTokenNewController.js';

const COPY_MESSAGES = new Map([
  [COPY_DONE, { className: 'text-success', text: 'Copied to the clipboard.' }],
  [COPY_FAILED, { className: 'text-danger', text: 'Copy failed — select the token and copy it manually.' }],
]);

/**
 * Render the outcome of the last copy attempt, if any.
 *
 * @param {string} copyState - The copy state.
 * @returns {React.ReactElement|null} The message, or `null` before any copy attempt.
 */
function renderCopyStatus(copyState) {
  const message = COPY_MESSAGES.get(copyState);

  if (!message) {
    return null;
  }

  return <div className={`form-text ${message.className}`} role="status">{message.text}</div>;
}

/**
 * One-time panel shown after an API token is created.
 *
 * @description Shows the plain token (read-only) with a warning that it cannot be shown
 * again, a Copy button and a Done link back to the list. The token only comes from props: the
 * panel never stores it.
 * @param {object} props - Component props.
 * @param {string} props.token - The plain token.
 * @param {string} props.copyState - The copy state (`idle`, `copied` or `failed`).
 * @param {Function} props.onCopy - Copy click handler.
 * @returns {React.ReactElement} The rendered panel.
 */
export default function ApiTokenCreatedPanel({ token, copyState, onCopy }) {
  return (
    <div>
      <div className="alert alert-warning">Copy this token now. It cannot be shown again.</div>
      <div className="mb-3">
        <label className="form-label" htmlFor="api-token-created">API token</label>
        <input
          id="api-token-created"
          type="text"
          className="form-control font-monospace"
          value={token}
          readOnly
          autoComplete="off"
          spellCheck={false}
        />
        {renderCopyStatus(copyState)}
      </div>
      <button type="button" className="btn btn-secondary" onClick={onCopy}>Copy</button>
      <a href="#/api-tokens" className="btn btn-primary ms-2">Done</a>
    </div>
  );
}
