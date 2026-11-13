import SmtpConnectionTestController, {
  CLOSED_TEST_STATE, buildRecipientUpdate,
} from '../../../../../../../../assets/js/components/resources/smtpConnections/pages/controllers/SmtpConnectionTestController.js';
import ApiError from '../../../../../../../../assets/js/client/ApiError.js';
import loadFixture, { loadApiError } from '../../../../../../../support/loadFixture.js';

describe('SmtpConnectionTestController', () => {
  let setTestState;
  let client;
  let controller;
  let state;

  const { smtpConnection: connection } = loadFixture('smtpConnections/item');
  const sent = loadFixture('smtpConnections/testSent');

  beforeEach(() => {
    state = { ...CLOSED_TEST_STATE, connection };
    setTestState = jasmine.createSpy('setTestState').and.callFake((update) => {
      state = typeof update === 'function' ? update(state) : update;
    });
    client = jasmine.createSpyObj('client', ['test']);
    controller = new SmtpConnectionTestController(setTestState, client);
  });

  describe('#open', () => {
    it('opens the modal for the connection with a clean state', () => {
      state = { ...CLOSED_TEST_STATE, to: 'old@example.com', error: 'old' };

      controller.open(connection);

      expect(state).toEqual({ ...CLOSED_TEST_STATE, connection });
    });
  });

  describe('#close', () => {
    it('resets the modal state', () => {
      controller.close();

      expect(state).toEqual(CLOSED_TEST_STATE);
    });
  });

  describe('#submit', () => {
    it('sends the trimmed recipient', async () => {
      client.test.and.resolveTo(sent);

      await controller.submit({ connection, to: '  bob@example.com ' });

      expect(client.test).toHaveBeenCalledWith(12, { to: 'bob@example.com' });
    });

    it('omits the recipient when blank', async () => {
      client.test.and.resolveTo(sent);

      await controller.submit({ connection, to: '   ' });

      expect(client.test).toHaveBeenCalledWith(12, { to: undefined });
    });

    it('is busy while the request runs and clears the previous outcome', async () => {
      state = { ...state, error: 'old', smtp: { code: 1, message: 'x' }, fieldError: 'bad' };
      let busyState;
      client.test.and.callFake(() => {
        busyState = state;
        return Promise.resolve(sent);
      });

      await controller.submit({ connection, to: '' });

      expect(busyState).toEqual(jasmine.objectContaining({
        sending: true, result: null, error: null, smtp: null, fieldError: null,
      }));
      expect(state.sending).toBeFalse();
    });

    it('stores the SMTP reply on success', async () => {
      client.test.and.resolveTo(sent);

      await controller.submit({ connection, to: '' });

      expect(state.result).toEqual({ code: 250, message: 'OK: queued as 1a2b3c' });
      expect(state.error).toBeNull();
    });

    it('shows the SMTP failure with the server reply', async () => {
      client.test.and.rejectWith(loadApiError('smtpConnections/smtpFailure'));

      await controller.submit({ connection, to: '' });

      expect(state).toEqual(jasmine.objectContaining({
        sending: false,
        result: null,
        error: 'The SMTP server rejected the credentials',
        smtp: { code: 535, message: '5.7.8 Authentication credentials invalid' },
      }));
    });

    it('shows the SMTP failure without a server reply when none was given', async () => {
      client.test.and.rejectWith(loadApiError('smtpConnections/sendTimeFailure'));

      await controller.submit({ connection, to: '' });

      expect(state).toEqual(jasmine.objectContaining({
        error: 'The SMTP connection has credentials but no encryption; set encryption to tls or ssl', smtp: null,
      }));
    });

    it('puts validation details on the recipient field', async () => {
      client.test.and.rejectWith(new ApiError(400, 'x', 'VALIDATION_FAILED', ['to must be an email']));

      await controller.submit({ connection, to: 'nope' });

      expect(state).toEqual(jasmine.objectContaining({ fieldError: 'to must be an email', error: null }));
    });

    it('falls back to the message for a validation failure without details', async () => {
      client.test.and.rejectWith(new ApiError(400, 'to must be an email', 'VALIDATION_FAILED'));

      await controller.submit({ connection, to: 'nope' });

      expect(state.fieldError).toBe('to must be an email');
    });

    it('shows any other error as a generic error', async () => {
      client.test.and.rejectWith(loadApiError('smtpConnections/notFound'));

      await controller.submit({ connection, to: '' });

      expect(state).toEqual(jasmine.objectContaining({ error: 'SMTP connection not found', smtp: null }));
    });

    it('only ends the busy state when the session ended', async () => {
      client.test.and.resolveTo(undefined);

      await controller.submit({ connection, to: '' });

      expect(state).toEqual(jasmine.objectContaining({ sending: false, result: null, error: null }));
    });
  });

  describe('buildRecipientUpdate', () => {
    it('sets the recipient, keeping the rest of the state', () => {
      expect(buildRecipientUpdate('bob@example.com')(state)).toEqual({ ...state, to: 'bob@example.com' });
    });
  });
});
