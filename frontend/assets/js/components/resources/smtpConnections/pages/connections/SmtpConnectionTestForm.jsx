import Modal from 'react-bootstrap/cjs/Modal.js';

const RECIPIENT_ID = 'smtp-connection-test-to';

/**
 * Format an SMTP server reply as `code message`.
 *
 * @param {{code: number, message: string}} smtp - The SMTP server reply.
 * @returns {string} The formatted reply.
 */
function formatSmtp(smtp) {
  return `${smtp.code} ${smtp.message}`;
}

/**
 * Render the SMTP server reply of a failed test email, when present.
 *
 * @param {({code: number, message: string}|null)} smtp - The SMTP server reply.
 * @returns {React.ReactElement|null} The reply, or `null` when there is none.
 */
function renderErrorSmtp(smtp) {
  if (!smtp) {
    return null;
  }

  return <div className="mt-1">SMTP reply: {formatSmtp(smtp)}</div>;
}

/**
 * Render the outcome of the last test email: the success alert with the SMTP reply, the error
 * alert (with the SMTP reply when present), or nothing.
 *
 * @param {object} state - Test-email modal state.
 * @returns {React.ReactElement|null} The outcome alert, or `null` when there is none.
 */
function renderOutcome(state) {
  if (state.result) {
    return (
      <div className="alert alert-success">
        Test email sent. SMTP reply: {formatSmtp(state.result)}
      </div>
    );
  }

  if (state.error) {
    return (
      <div className="alert alert-danger">
        {state.error}
        {renderErrorSmtp(state.smtp)}
      </div>
    );
  }

  return null;
}

/**
 * Render the optional recipient field, with its help text and validation error.
 *
 * @param {object} state - Test-email modal state.
 * @param {Function} onRecipientChange - Change handler for the input.
 * @returns {React.ReactElement} The rendered field.
 */
function renderRecipientField(state, onRecipientChange) {
  return (
    <div className="mb-3">
      <label className="form-label" htmlFor={RECIPIENT_ID}>Recipient</label>
      <input
        id={RECIPIENT_ID}
        type="email"
        className={`form-control${state.fieldError ? ' is-invalid' : ''}`}
        value={state.to}
        onChange={onRecipientChange}
      />
      {state.fieldError && <div className="invalid-feedback">{state.fieldError}</div>}
      <div className="form-text">Leave empty to send the test email to your own email address.</div>
    </div>
  );
}

/**
 * Content of the test-email modal: the connection being tested, the optional recipient, the
 * outcome of the last attempt, and the Close / Send buttons. Send is disabled (and reads
 * "Sending…") while the request runs, since it is synchronous and can be slow.
 *
 * @param {object} props - Component props.
 * @param {{connection: (object|null), to: string, sending: boolean,
 *   result: (object|null), error: (string|null), smtp: (object|null),
 *   fieldError: (string|null)}} props.state - Test-email modal state.
 * @param {Function} props.onClose - Closes the modal.
 * @param {Function} props.onRecipientChange - Change handler for the recipient input.
 * @param {Function} props.onSubmit - Submit handler of the form.
 * @returns {React.ReactElement} The rendered form.
 */
export default function SmtpConnectionTestForm({
  state, onClose, onRecipientChange, onSubmit,
}) {
  return (
    <form onSubmit={onSubmit} noValidate>
      <Modal.Header closeButton>
        <Modal.Title>Send test email</Modal.Title>
      </Modal.Header>
      <Modal.Body>
        <p>Sends a test email through {state.connection?.name}.</p>
        {renderOutcome(state)}
        {renderRecipientField(state, onRecipientChange)}
      </Modal.Body>
      <Modal.Footer>
        <button type="button" className="btn btn-secondary" onClick={onClose}>Close</button>
        <button type="submit" className="btn btn-primary" disabled={state.sending}>
          {state.sending ? 'Sending…' : 'Send'}
        </button>
      </Modal.Footer>
    </form>
  );
}
