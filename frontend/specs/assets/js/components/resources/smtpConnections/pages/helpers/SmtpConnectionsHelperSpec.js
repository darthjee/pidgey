import SmtpConnectionsHelper from '../../../../../../../../assets/js/components/resources/smtpConnections/pages/helpers/SmtpConnectionsHelper.jsx';
import { CLOSED_TEST_STATE } from '../../../../../../../../assets/js/components/resources/smtpConnections/pages/controllers/SmtpConnectionTestController.js';
import { renderedOutput } from '../../../../../../../support/renderedOutput.js';

describe('SmtpConnectionsHelper', () => {
  const active = {
    id: 1, name: 'Mailpit', host: 'mailpit', port: 1025, encryption: 'none',
    username: null, hasPassword: false, fromAddress: 'noreply@example.com', active: true,
  };
  const inactive = {
    id: 2, name: 'Relay', host: 'smtp.example.com', port: 587, encryption: 'tls',
    username: 'relay-user', hasPassword: true, fromAddress: 'relay@example.com', active: false,
  };

  const buildHandlers = () => ({
    onActivate: jasmine.createSpy('onActivate').and.returnValue(jasmine.createSpy('handler')),
    onDelete: jasmine.createSpy('onDelete').and.returnValue(jasmine.createSpy('handler')),
    onOpenTest: jasmine.createSpy('onOpenTest').and.returnValue(jasmine.createSpy('handler')),
    onCloseTest: jasmine.createSpy('onCloseTest'),
    onRecipientChange: jasmine.createSpy('onRecipientChange'),
    onSubmitTest: jasmine.createSpy('onSubmitTest'),
  });
  const buildState = (overrides = {}) => ({
    connections: [], loading: false, loadError: null, listError: null, test: CLOSED_TEST_STATE, ...overrides,
  });
  const renderPage = (state, handlers = buildHandlers()) => renderedOutput(
    SmtpConnectionsHelper.render(state, handlers),
  );

  describe('.render', () => {
    it('renders the heading and the New connection button', () => {
      const page = renderPage(buildState());

      expect(page.containsElement('h1', 'SMTP connections')).withContext('heading').toBeTrue();
      expect(page.containsAttribute('href', '#/smtp-connections/new')).withContext('href').toBeTrue();
      expect(page.containsElement('a', 'New connection')).withContext('label').toBeTrue();
    });

    it('renders a loading indicator while loading', () => {
      const page = renderPage(buildState({ loading: true }));

      expect(page.contains('spinner-border')).withContext('spinner').toBeTrue();
      expect(page.containsTag('table')).withContext('table').toBeFalse();
    });

    it('renders the load error', () => {
      const page = renderPage(buildState({ loadError: 'Server error' }));

      expect(page.containsElement('div', 'Server error')).withContext('message').toBeTrue();
      expect(page.contains('alert-warning')).withContext('notice').toBeFalse();
    });

    it('renders the empty state and the no-active notice when there is no connection', () => {
      const page = renderPage(buildState());

      expect(page.contains('No SMTP connections yet.')).withContext('empty').toBeTrue();
      expect(page.contains('alert-warning')).withContext('notice').toBeTrue();
    });

    it('renders the no-active notice when no listed connection is active', () => {
      expect(renderPage(buildState({ connections: [inactive] })).contains('alert-warning')).toBeTrue();
    });

    it('renders no notice when a connection is active', () => {
      expect(renderPage(buildState({ connections: [active, inactive] })).contains('alert-warning')).toBeFalse();
    });

    it('renders the list error', () => {
      const page = renderPage(buildState({ connections: [active], listError: 'Activation failed' }));

      expect(page.containsElement('div', 'Activation failed')).toBeTrue();
    });

    it('renders a row per connection with its settings', () => {
      const page = renderPage(buildState({ connections: [active, inactive] }));

      expect(page.containsAttribute('href', '#/smtp-connections/2/edit')).withContext('edit link').toBeTrue();
      expect(page.containsElement('a', 'Relay')).withContext('name').toBeTrue();
      expect(page.containsElement('td', 'smtp.example.com:587')).withContext('server').toBeTrue();
      expect(page.containsElement('td', 'tls')).withContext('encryption').toBeTrue();
      expect(page.containsElement('td', 'relay@example.com')).withContext('from').toBeTrue();
    });

    it('shows whether credentials are set', () => {
      expect(renderPage(buildState({ connections: [active] })).containsElement('td', 'None')).withContext('none').toBeTrue();
      expect(renderPage(buildState({ connections: [inactive] })).containsElement('td', 'Set')).withContext('set').toBeTrue();
      expect(renderPage(buildState({ connections: [{ ...active, hasPassword: true }] })).containsElement('td', 'Set'))
        .withContext('password only').toBeTrue();
    });

    it('shows the Active badge and hides Activate only on the active connection', () => {
      expect(renderPage(buildState({ connections: [active] })).containsInOrder('Mailpit', 'Active</span>'))
        .withContext('badge').toBeTrue();
      expect(renderPage(buildState({ connections: [active] })).containsElement('button', 'Activate'))
        .withContext('no activate').toBeFalse();
      expect(renderPage(buildState({ connections: [inactive] })).contains('badge')).withContext('no badge').toBeFalse();
      expect(renderPage(buildState({ connections: [inactive] })).containsElement('button', 'Activate'))
        .withContext('activate').toBeTrue();
    });

    it('binds the row actions to their connection', () => {
      const handlers = buildHandlers();

      const page = renderPage(buildState({ connections: [inactive] }), handlers);

      expect(page.containsElement('button', 'Delete')).withContext('delete').toBeTrue();
      expect(page.contains('Send test email')).withContext('test').toBeTrue();
      expect(handlers.onActivate).toHaveBeenCalledWith(2);
      expect(handlers.onDelete).toHaveBeenCalledWith(inactive);
      expect(handlers.onOpenTest).toHaveBeenCalledWith(inactive);
    });

    it('renders the test-email modal with the test state and handlers', () => {
      const handlers = buildHandlers();
      const element = SmtpConnectionsHelper.render(buildState(), handlers);
      const modal = element.props.children[2];

      expect(modal.props).toEqual({
        state: CLOSED_TEST_STATE,
        onClose: handlers.onCloseTest,
        onRecipientChange: handlers.onRecipientChange,
        onSubmit: handlers.onSubmitTest,
      });
    });
  });
});
