import Modal from 'react-bootstrap/cjs/Modal.js';
import SmtpConnectionTestForm from './SmtpConnectionTestForm.jsx';

/**
 * Test-email modal of the SMTP connections list: shown while a connection is selected for a
 * test, with the {@link SmtpConnectionTestForm} as its content.
 *
 * @param {object} props - Component props.
 * @param {{connection: (object|null)}} props.state - Test-email modal state; the modal is
 *   open while `connection` is set.
 * @param {Function} props.onClose - Closes the modal (and resets its state).
 * @param {Function} props.onRecipientChange - Change handler for the recipient input.
 * @param {Function} props.onSubmit - Submit handler of the test-email form.
 * @returns {React.ReactElement} The rendered modal (nothing visible while closed).
 */
export default function SmtpConnectionTestModal({
  state, onClose, onRecipientChange, onSubmit,
}) {
  return (
    <Modal show={state.connection !== null} onHide={onClose}>
      <SmtpConnectionTestForm
        state={state}
        onClose={onClose}
        onRecipientChange={onRecipientChange}
        onSubmit={onSubmit}
      />
    </Modal>
  );
}
