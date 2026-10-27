import ApiClient from '../../../../assets/js/client/ApiClient.js';
import ApiError from '../../../../assets/js/client/ApiError.js';
import ApiTokensClient from '../../../../assets/js/client/ApiTokensClient.js';

describe('ApiTokensClient', () => {
  const apiToken = {
    id: 5, name: 'Shop backend', tokenPrefix: 'pdg_', scopes: ['send'],
    createdAt: '2026-10-17T12:00:00.000Z', lastUsedAt: null, revokedAt: null,
  };

  describe('.list', () => {
    it('gets the collection endpoint', async () => {
      spyOn(ApiClient, 'getJson').and.resolveTo({ apiTokens: [apiToken] });

      const response = await ApiTokensClient.list();

      expect(ApiClient.getJson).toHaveBeenCalledWith('/api-tokens.json');
      expect(response).toEqual({ apiTokens: [apiToken] });
    });

    it('passes through an undefined result', async () => {
      spyOn(ApiClient, 'getJson').and.resolveTo(undefined);

      expect(await ApiTokensClient.list()).toBeUndefined();
    });

    it('passes through an ApiError', async () => {
      const error = new ApiError(500, 'Boom');
      spyOn(ApiClient, 'getJson').and.rejectWith(error);

      await expectAsync(ApiTokensClient.list()).toBeRejectedWith(error);
    });
  });

  describe('.create', () => {
    const created = { apiToken: { ...apiToken, token: 'pdg_secret' } };

    it('posts name and scopes to the collection endpoint', async () => {
      spyOn(ApiClient, 'postJson').and.resolveTo(created);

      const response = await ApiTokensClient.create({ name: 'Shop backend', scopes: ['send'] });

      expect(ApiClient.postJson).toHaveBeenCalledWith('/api-tokens.json', { name: 'Shop backend', scopes: ['send'] });
      expect(response).toEqual(created);
    });

    it('never sends unknown keys', async () => {
      spyOn(ApiClient, 'postJson').and.resolveTo(created);

      await ApiTokensClient.create({ name: 'Shop', scopes: ['send'], id: 3, token: 'x' });

      expect(ApiClient.postJson).toHaveBeenCalledWith('/api-tokens.json', { name: 'Shop', scopes: ['send'] });
    });

    it('drops undefined keys', async () => {
      spyOn(ApiClient, 'postJson').and.resolveTo(created);

      await ApiTokensClient.create({ name: 'Shop' });

      expect(ApiClient.postJson).toHaveBeenCalledWith('/api-tokens.json', { name: 'Shop' });
    });

    it('sends an empty body when called without attributes', async () => {
      spyOn(ApiClient, 'postJson').and.resolveTo(created);

      await ApiTokensClient.create();

      expect(ApiClient.postJson).toHaveBeenCalledWith('/api-tokens.json', {});
    });

    it('passes through an ApiError', async () => {
      const error = new ApiError(400, 'Validation failed', 'VALIDATION_FAILED', ['name should not be empty']);
      spyOn(ApiClient, 'postJson').and.rejectWith(error);

      await expectAsync(ApiTokensClient.create({ name: '' })).toBeRejectedWith(error);
    });

    it('passes through an undefined result', async () => {
      spyOn(ApiClient, 'postJson').and.resolveTo(undefined);

      expect(await ApiTokensClient.create({ name: 'Shop', scopes: ['send'] })).toBeUndefined();
    });
  });

  describe('.revoke', () => {
    it('deletes the item endpoint without a body', async () => {
      spyOn(ApiClient, 'deleteJson').and.resolveTo({});

      const response = await ApiTokensClient.revoke(5);

      expect(ApiClient.deleteJson).toHaveBeenCalledWith('/api-tokens/5.json');
      expect(ApiClient.deleteJson.calls.mostRecent().args.length).toBe(1);
      expect(response).toEqual({});
    });

    it('passes through an ApiError', async () => {
      const error = new ApiError(404, 'API token not found', 'NOT_FOUND');
      spyOn(ApiClient, 'deleteJson').and.rejectWith(error);

      await expectAsync(ApiTokensClient.revoke(5)).toBeRejectedWith(error);
    });

    it('passes through an undefined result', async () => {
      spyOn(ApiClient, 'deleteJson').and.resolveTo(undefined);

      expect(await ApiTokensClient.revoke(5)).toBeUndefined();
    });
  });
});
