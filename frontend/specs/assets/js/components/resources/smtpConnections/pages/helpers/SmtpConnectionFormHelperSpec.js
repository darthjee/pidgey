import SmtpConnectionFormHelper from '../../../../../../../../assets/js/components/resources/smtpConnections/pages/helpers/SmtpConnectionFormHelper.jsx';
import { renderedOutput } from '../../../../../../../support/renderedOutput.js';

describe('SmtpConnectionFormHelper', () => {
  const buildHandlers = () => ({
    onSubmit: jasmine.createSpy('onSubmit'),
    onChange: jasmine.createSpy('onChange').and.returnValue(jasmine.createSpy('handler')),
    onRemoveCredentials: jasmine.createSpy('onRemoveCredentials'),
  });
  const buildState = (overrides = {}) => ({
    mode: 'create',
    name: '',
    host: '',
    port: '',
    encryption: '',
    username: '',
    password: '',
    fromAddress: '',
    stored: null,
    loading: false,
    loadError: null,
    notFound: false,
    fieldErrors: {},
    submitError: null,
    success: false,
    ...overrides,
  });
  const renderPage = (state, handlers = buildHandlers()) => renderedOutput(
    SmtpConnectionFormHelper.render(state, handlers),
  );
  const stored = { username: 'user', hasPassword: true };

  describe('.render', () => {
    it('titles the page per mode', () => {
      expect(renderPage(buildState()).containsElement('h1', 'New SMTP connection')).withContext('create').toBeTrue();
      expect(renderPage(buildState({ mode: 'edit', stored })).containsElement('h1', 'Edit SMTP connection'))
        .withContext('edit').toBeTrue();
    });

    it('renders a loading indicator while loading', () => {
      const page = renderPage(buildState({ mode: 'edit', loading: true }));

      expect(page.contains('spinner-border')).withContext('spinner').toBeTrue();
      expect(page.containsTag('form')).withContext('form').toBeFalse();
    });

    it('renders the not-found message with a link back to the list', () => {
      const page = renderPage(buildState({ mode: 'edit', notFound: true }));

      expect(page.contains('SMTP connection not found.')).withContext('message').toBeTrue();
      expect(page.containsAttribute('href', '#/smtp-connections')).withContext('link').toBeTrue();
      expect(page.containsTag('form')).withContext('form').toBeFalse();
    });

    it('renders the load error', () => {
      const page = renderPage(buildState({ mode: 'edit', loadError: 'Server error' }));

      expect(page.containsElement('div', 'Server error')).withContext('message').toBeTrue();
      expect(page.containsTag('form')).withContext('form').toBeFalse();
    });

    it('renders every field', () => {
      const handlers = buildHandlers();
      const page = renderPage(buildState(), handlers);

      ['Name', 'Host', 'Port', 'Encryption', 'Username', 'Password', 'From address'].forEach((label) => {
        expect(page.containsElement('label', label)).withContext(label).toBeTrue();
      });
      ['name', 'host', 'port', 'encryption', 'username', 'password', 'fromAddress'].forEach((field) => {
        expect(handlers.onChange).withContext(field).toHaveBeenCalledWith(field);
      });
    });

    it('renders the port as a number input and the password as a new password', () => {
      const page = renderPage(buildState());

      expect(page.contains('type="number"')).withContext('port').toBeTrue();
      expect(page.contains('type="password" autoComplete="new-password"')).withContext('password').toBeTrue();
    });

    it('renders the encryption select with every mode', () => {
      const page = renderPage(buildState({ encryption: 'ssl' }));

      expect(page.containsElement('option', 'none')).withContext('none').toBeTrue();
      expect(page.containsElement('option', 'tls')).withContext('tls').toBeTrue();
      expect(page.contains('<option value="ssl" selected="">ssl</option>')).withContext('selected').toBeTrue();
    });

    it('states that new connections start inactive, on create only', () => {
      const notice = 'New connections are created inactive';

      expect(renderPage(buildState()).contains(notice)).withContext('create').toBeTrue();
      expect(renderPage(buildState({ mode: 'edit', stored })).contains(notice)).withContext('edit').toBeFalse();
    });

    it('shows whether a password is stored, on edit only', () => {
      expect(renderPage(buildState({ mode: 'edit', stored })).contains('Password: set.')).withContext('set').toBeTrue();
      expect(renderPage(buildState({ mode: 'edit', stored: { username: null, hasPassword: false } }))
        .contains('Password: not set.')).withContext('not set').toBeTrue();
      expect(renderPage(buildState()).contains('Password:')).withContext('create').toBeFalse();
    });

    it('offers Remove credentials only when credentials are stored', () => {
      expect(renderPage(buildState({ mode: 'edit', stored })).contains('Remove credentials')).withContext('edit').toBeTrue();
      expect(renderPage(buildState()).contains('Remove credentials')).withContext('create').toBeFalse();
    });

    it('renders the field errors', () => {
      const page = renderPage(buildState({
        fieldErrors: { encryption: 'Encryption is required', password: 'pairing', name: 'Name is required' },
      }));

      expect(page.containsElement('div', 'Encryption is required')).withContext('encryption').toBeTrue();
      expect(page.containsElement('div', 'pairing')).withContext('password').toBeTrue();
      expect(page.containsElement('div', 'Name is required')).withContext('name').toBeTrue();
      expect(page.contains('form-select is-invalid')).withContext('select class').toBeTrue();
    });

    it('renders the submit error and the success confirmation', () => {
      expect(renderPage(buildState({ submitError: 'Server error' })).containsElement('div', 'Server error'))
        .withContext('error').toBeTrue();
      expect(renderPage(buildState({ mode: 'edit', stored, success: true })).contains('SMTP connection saved.'))
        .withContext('success').toBeTrue();
    });

    it('renders the Save button and a Cancel link back to the list', () => {
      const page = renderPage(buildState());

      expect(page.containsElement('button', 'Save')).withContext('save').toBeTrue();
      expect(page.containsElement('a', 'Cancel')).withContext('cancel').toBeTrue();
    });
  });
});
