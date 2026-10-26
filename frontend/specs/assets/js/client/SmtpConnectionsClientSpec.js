import ApiClient from '../../../../assets/js/client/ApiClient.js';
import SmtpConnectionsClient from '../../../../assets/js/client/SmtpConnectionsClient.js';

describe('SmtpConnectionsClient', () => {
  const smtpConnection = {
    id: 12, name: 'Mailpit', host: 'mailpit', port: 1025, encryption: 'none',
    username: null, hasPassword: false, fromAddress: 'noreply@example.com', active: true,
  };
  const attributes = {
    name: 'Relay', host: 'smtp.example.com', port: 587, encryption: 'tls',
    username: 'user', password: 's3cret', fromAddress: 'noreply@example.com',
  };

  describe('.list', () => {
    it('gets the collection endpoint', async () => {
      spyOn(ApiClient, 'getJson').and.resolveTo({ smtpConnections: [smtpConnection] });

      const response = await SmtpConnectionsClient.list();

      expect(ApiClient.getJson).toHaveBeenCalledWith('/smtp-connections.json');
      expect(response).toEqual({ smtpConnections: [smtpConnection] });
    });

    it('passes through an undefined result', async () => {
      spyOn(ApiClient, 'getJson').and.resolveTo(undefined);

      expect(await SmtpConnectionsClient.list()).toBeUndefined();
    });
  });

  describe('.create', () => {
    it('posts the accepted attributes to the collection endpoint', async () => {
      spyOn(ApiClient, 'postJson').and.resolveTo({ smtpConnection });

      const response = await SmtpConnectionsClient.create(attributes);

      expect(ApiClient.postJson).toHaveBeenCalledWith('/smtp-connections.json', attributes);
      expect(response).toEqual({ smtpConnection });
    });

    it('never sends active or unknown keys', async () => {
      spyOn(ApiClient, 'postJson').and.resolveTo({ smtpConnection });

      await SmtpConnectionsClient.create({ ...attributes, active: true, id: 3, hasPassword: true });

      expect(ApiClient.postJson).toHaveBeenCalledWith('/smtp-connections.json', attributes);
    });

    it('drops undefined keys', async () => {
      spyOn(ApiClient, 'postJson').and.resolveTo({ smtpConnection });

      await SmtpConnectionsClient.create({ ...attributes, username: undefined, password: undefined });

      expect(ApiClient.postJson).toHaveBeenCalledWith('/smtp-connections.json', {
        name: 'Relay', host: 'smtp.example.com', port: 587, encryption: 'tls', fromAddress: 'noreply@example.com',
      });
    });
  });

  describe('.get', () => {
    it('gets the item endpoint', async () => {
      spyOn(ApiClient, 'getJson').and.resolveTo({ smtpConnection });

      const response = await SmtpConnectionsClient.get(12);

      expect(ApiClient.getJson).toHaveBeenCalledWith('/smtp-connections/12.json');
      expect(response).toEqual({ smtpConnection });
    });
  });

  describe('.update', () => {
    it('patches the item endpoint with only the given keys', async () => {
      spyOn(ApiClient, 'patchJson').and.resolveTo({ smtpConnection });

      await SmtpConnectionsClient.update(12, { port: 465, encryption: 'ssl', active: false });

      expect(ApiClient.patchJson).toHaveBeenCalledWith('/smtp-connections/12.json', { port: 465, encryption: 'ssl' });
    });

    it('keeps empty strings, which clear the credentials', async () => {
      spyOn(ApiClient, 'patchJson').and.resolveTo({ smtpConnection });

      await SmtpConnectionsClient.update(12, { username: '', password: '' });

      expect(ApiClient.patchJson).toHaveBeenCalledWith('/smtp-connections/12.json', { username: '', password: '' });
    });

    it('passes through an undefined result', async () => {
      spyOn(ApiClient, 'patchJson').and.resolveTo(undefined);

      expect(await SmtpConnectionsClient.update(12, { name: 'x' })).toBeUndefined();
    });
  });

  describe('.remove', () => {
    it('deletes the item endpoint without a body', async () => {
      spyOn(ApiClient, 'deleteJson').and.resolveTo({});

      await SmtpConnectionsClient.remove(12);

      expect(ApiClient.deleteJson).toHaveBeenCalledWith('/smtp-connections/12.json');
    });
  });

  describe('.activate', () => {
    it('posts to the activate endpoint without a body', async () => {
      spyOn(ApiClient, 'postJson').and.resolveTo({ smtpConnection });

      const response = await SmtpConnectionsClient.activate(12);

      expect(ApiClient.postJson).toHaveBeenCalledWith('/smtp-connections/12/activate.json');
      expect(ApiClient.postJson.calls.mostRecent().args.length).toBe(1);
      expect(response).toEqual({ smtpConnection });
    });
  });

  describe('.test', () => {
    const result = { status: 'sent', smtp: { code: 250, message: 'OK' } };

    it('posts the recipient when given', async () => {
      spyOn(ApiClient, 'postJson').and.resolveTo(result);

      const response = await SmtpConnectionsClient.test(12, { to: 'bob@example.com' });

      expect(ApiClient.postJson).toHaveBeenCalledWith('/smtp-connections/12/test.json', { to: 'bob@example.com' });
      expect(response).toEqual(result);
    });

    it('posts an empty body when no recipient is given', async () => {
      spyOn(ApiClient, 'postJson').and.resolveTo(result);

      await SmtpConnectionsClient.test(12);

      expect(ApiClient.postJson).toHaveBeenCalledWith('/smtp-connections/12/test.json', {});
    });

    it('posts an empty body when the recipient is empty', async () => {
      spyOn(ApiClient, 'postJson').and.resolveTo(result);

      await SmtpConnectionsClient.test(12, { to: '' });

      expect(ApiClient.postJson).toHaveBeenCalledWith('/smtp-connections/12/test.json', {});
    });
  });
});
