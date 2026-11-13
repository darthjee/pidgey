import SmtpConnectionFormController from '../../../../../../../../assets/js/components/resources/smtpConnections/pages/controllers/SmtpConnectionFormController.js';
import SmtpConnectionFormRules, {
  ENCRYPTION_REQUIRED_MESSAGE,
} from '../../../../../../../../assets/js/components/resources/smtpConnections/pages/controllers/SmtpConnectionFormRules.js';
import ApiError from '../../../../../../../../assets/js/client/ApiError.js';
import { installFakeWindow, uninstallFakeWindow } from '../../../../../../../support/fakeWindow.js';
import loadFixture, { loadApiError } from '../../../../../../../support/loadFixture.js';

describe('SmtpConnectionFormController', () => {
  let setters;
  let client;
  let navigate;
  let controller;

  const item = loadFixture('smtpConnections/list').smtpConnections[1];
  const itemFields = {
    name: 'Office relay', host: 'smtp.example.com', port: '587', encryption: 'tls',
    username: 'relay-user', password: '', fromAddress: 'noreply@example.com',
  };
  const stored = {
    username: 'relay-user', hasPassword: true, host: 'smtp.example.com', port: 587, encryption: 'tls',
  };

  beforeEach(() => {
    setters = jasmine.createSpyObj('setters', [
      'setFields', 'setStored', 'setLoadState', 'setFieldErrors', 'setSubmitError', 'setSuccess',
    ]);
    client = jasmine.createSpyObj('client', ['get', 'create', 'update']);
    navigate = jasmine.createSpy('navigate');
    controller = new SmtpConnectionFormController({ ...setters, client, navigate });
  });

  describe('#load', () => {
    it('fills the form from the item, without the password, and the stored credentials', async () => {
      client.get.and.resolveTo(loadFixture('smtpConnections/item'));

      await controller.load('12');

      expect(client.get).toHaveBeenCalledWith('12');
      expect(setters.setFields).toHaveBeenCalledWith({
        name: 'Mailpit', host: 'mailpit', port: '1025', encryption: 'none',
        username: '', password: '', fromAddress: 'noreply@example.com',
      });
      expect(setters.setStored).toHaveBeenCalledWith({
        username: null, hasPassword: false, host: 'mailpit', port: 1025, encryption: 'none',
      });
      expect(setters.setLoadState).toHaveBeenCalledWith({ loading: false, loadError: null, notFound: false });
    });

    it('marks an unknown connection as not found', async () => {
      client.get.and.rejectWith(loadApiError('smtpConnections/notFound'));

      await controller.load('99');

      expect(setters.setLoadState).toHaveBeenCalledWith({ loading: false, loadError: null, notFound: true });
    });

    it('stores any other load error', async () => {
      client.get.and.rejectWith(new ApiError(500, 'Server error'));

      await controller.load('5');

      expect(setters.setLoadState).toHaveBeenCalledWith({ loading: false, loadError: 'Server error', notFound: false });
    });

    it('stays idle when the session ended', async () => {
      client.get.and.resolveTo(undefined);

      await controller.load('5');

      expect(setters.setFields).not.toHaveBeenCalled();
      expect(setters.setLoadState).toHaveBeenCalledWith({ loading: false, loadError: null, notFound: false });
    });
  });

  describe('#handleSubmit', () => {
    it('stops on validation errors, without calling the API', async () => {
      await controller.handleSubmit(undefined, { ...itemFields, name: '' }, null);

      expect(setters.setFieldErrors).toHaveBeenCalledWith(jasmine.objectContaining({ name: 'Name is required' }));
      expect(client.create).not.toHaveBeenCalled();
    });

    it('creates with the create payload, then goes back to the list', async () => {
      client.create.and.resolveTo({ smtpConnection: item });
      const fields = { ...itemFields, password: 's3cret' };

      await controller.handleSubmit(undefined, fields, null);

      expect(setters.setSuccess).toHaveBeenCalledWith(false);
      expect(setters.setSubmitError).toHaveBeenCalledWith(null);
      expect(setters.setFieldErrors).toHaveBeenCalledWith({});
      expect(client.create).toHaveBeenCalledWith(SmtpConnectionFormRules.buildCreatePayload(fields));
      expect(navigate).toHaveBeenCalledWith('/smtp-connections');
    });

    it('stays on the form when the session ended during create', async () => {
      client.create.and.resolveTo(undefined);

      await controller.handleSubmit(undefined, { ...itemFields, password: 's3cret' }, null);

      expect(navigate).not.toHaveBeenCalled();
    });

    it('updates with the update payload, refills the form and confirms', async () => {
      client.update.and.resolveTo({ smtpConnection: { ...item, port: 465 } });
      const fields = { ...itemFields, port: '465', password: 'n3w' };

      await controller.handleSubmit('5', fields, stored);

      expect(client.update).toHaveBeenCalledWith('5', SmtpConnectionFormRules.buildUpdatePayload(fields, stored));
      expect(setters.setFields).toHaveBeenCalledWith({ ...itemFields, port: '465', password: '' });
      expect(setters.setStored).toHaveBeenCalledWith({ ...stored, port: 465 });
      expect(setters.setSuccess).toHaveBeenCalledWith(true);
    });

    it('changes nothing when the session ended during update', async () => {
      client.update.and.resolveTo(undefined);

      await controller.handleSubmit('5', itemFields, stored);

      expect(setters.setFields).not.toHaveBeenCalled();
      expect(setters.setSuccess).not.toHaveBeenCalledWith(true);
    });

    it('maps validation details to the fields, unmatched ones to the submit error', async () => {
      client.update.and.rejectWith(new ApiError(400, 'x', 'VALIDATION_FAILED', [
        'host must be a hostname or an IP address', 'something else',
      ]));

      await controller.handleSubmit('5', itemFields, stored);

      expect(setters.setFieldErrors).toHaveBeenCalledWith({ host: 'host must be a hostname or an IP address' });
      expect(setters.setSubmitError).toHaveBeenCalledWith('something else');
    });

    it('stops on the credential rules, without calling the API', async () => {
      await controller.handleSubmit(undefined, { ...itemFields, password: 's3cret', encryption: 'none' }, null);

      expect(setters.setFieldErrors).toHaveBeenCalledWith({ password: ENCRYPTION_REQUIRED_MESSAGE });
      expect(client.create).not.toHaveBeenCalled();
    });

    it('maps the server validation details, the credential ones to the password', async () => {
      client.update.and.rejectWith(loadApiError('smtpConnections/validationFailed'));

      await controller.handleSubmit('5', itemFields, stored);

      expect(setters.setFieldErrors).toHaveBeenCalledWith({
        port: 'port must be between 1 and 65535', password: ENCRYPTION_REQUIRED_MESSAGE,
      });
      expect(setters.setSubmitError).not.toHaveBeenCalledWith(jasmine.any(String));
    });

    it('sets no submit error when every detail matched a field', async () => {
      client.update.and.rejectWith(new ApiError(400, 'x', 'VALIDATION_FAILED', ['port must be an integer']));

      await controller.handleSubmit('5', itemFields, stored);

      expect(setters.setSubmitError).not.toHaveBeenCalledWith(jasmine.any(String));
    });

    it('shows any other error as a submit error', async () => {
      client.create.and.rejectWith(new ApiError(500, 'Server error'));

      await controller.handleSubmit(undefined, { ...itemFields, password: 's3cret' }, null);

      expect(setters.setSubmitError).toHaveBeenCalledWith('Server error');
      expect(navigate).not.toHaveBeenCalled();
    });

    it('shows a detail-less validation failure from the API as a submit error', async () => {
      client.create.and.rejectWith(new ApiError(400, 'Validation failed', 'VALIDATION_FAILED', []));

      await controller.handleSubmit(undefined, { ...itemFields, password: 's3cret' }, null);

      expect(setters.setSubmitError).toHaveBeenCalledWith('Validation failed');
    });
  });

  describe('#handleRemoveCredentials', () => {
    it('clears both credentials, refills the form and confirms', async () => {
      client.update.and.resolveTo({ smtpConnection: { ...item, username: null, hasPassword: false } });

      await controller.handleRemoveCredentials('5');

      expect(client.update).toHaveBeenCalledWith('5', { username: '', password: '' });
      expect(setters.setStored).toHaveBeenCalledWith({ ...stored, username: null, hasPassword: false });
      expect(setters.setSuccess).toHaveBeenCalledWith(true);
    });

    it('shows a failure as a submit error', async () => {
      client.update.and.rejectWith(loadApiError('smtpConnections/notFound'));

      await controller.handleRemoveCredentials('5');

      expect(setters.setSubmitError).toHaveBeenCalledWith('SMTP connection not found');
    });
  });

  describe('default navigation', () => {
    afterEach(() => uninstallFakeWindow());

    it('goes back to the list through the location hash', async () => {
      const fake = installFakeWindow({ location: { hash: '#/smtp-connections/new' } });
      client.create.and.resolveTo({ smtpConnection: item });
      const defaulted = new SmtpConnectionFormController({ ...setters, client });

      await defaulted.handleSubmit(undefined, { ...itemFields, password: 's3cret' }, null);

      expect(fake.location.hash).toBe('/smtp-connections');
    });
  });
});
