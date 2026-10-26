import Modal from 'react-bootstrap/cjs/Modal.js';
import SmtpConnectionTestModal from '../../../../../../../../assets/js/components/resources/smtpConnections/pages/connections/SmtpConnectionTestModal.jsx';
import SmtpConnectionTestForm from '../../../../../../../../assets/js/components/resources/smtpConnections/pages/connections/SmtpConnectionTestForm.jsx';
import { CLOSED_TEST_STATE } from '../../../../../../../../assets/js/components/resources/smtpConnections/pages/controllers/SmtpConnectionTestController.js';

describe('SmtpConnectionTestModal', () => {
  let handlers;

  beforeEach(() => {
    handlers = {
      onClose: jasmine.createSpy('onClose'),
      onRecipientChange: jasmine.createSpy('onRecipientChange'),
      onSubmit: jasmine.createSpy('onSubmit'),
    };
  });

  const render = (state) => SmtpConnectionTestModal({ state, ...handlers });

  it('renders a react-bootstrap Modal', () => {
    expect(render(CLOSED_TEST_STATE).type).toBe(Modal);
  });

  it('is hidden while no connection is selected', () => {
    expect(render(CLOSED_TEST_STATE).props.show).toBeFalse();
  });

  it('is shown while a connection is selected', () => {
    expect(render({ ...CLOSED_TEST_STATE, connection: { id: 1 } }).props.show).toBeTrue();
  });

  it('closes through onClose', () => {
    expect(render(CLOSED_TEST_STATE).props.onHide).toBe(handlers.onClose);
  });

  it('renders the test form with the state and handlers', () => {
    const form = render(CLOSED_TEST_STATE).props.children;

    expect(form.type).toBe(SmtpConnectionTestForm);
    expect(form.props).toEqual({ state: CLOSED_TEST_STATE, ...handlers });
  });
});
