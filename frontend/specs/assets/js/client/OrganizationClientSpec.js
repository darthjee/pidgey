import ApiClient from '../../../../assets/js/client/ApiClient.js';
import OrganizationClient from '../../../../assets/js/client/OrganizationClient.js';

describe('OrganizationClient', () => {
  const organization = {
    id: 1, name: 'Acme', createdAt: '2026-01-01T00:00:00.000Z', updatedAt: '2026-01-02T00:00:00.000Z',
  };

  describe('.get', () => {
    it('gets the organization endpoint', async () => {
      spyOn(ApiClient, 'getJson').and.resolveTo({ organization });

      await OrganizationClient.get();

      expect(ApiClient.getJson).toHaveBeenCalledWith('/organization.json');
    });

    it('resolves with the parsed response', async () => {
      spyOn(ApiClient, 'getJson').and.resolveTo({ organization });

      const response = await OrganizationClient.get();

      expect(response).toEqual({ organization });
    });
  });

  describe('.update', () => {
    it('patches the organization endpoint with the name', async () => {
      spyOn(ApiClient, 'patchJson').and.resolveTo({ organization });

      await OrganizationClient.update({ name: 'Acme' });

      expect(ApiClient.patchJson).toHaveBeenCalledWith('/organization.json', { name: 'Acme' });
    });

    it('resolves with the parsed response', async () => {
      spyOn(ApiClient, 'patchJson').and.resolveTo({ organization });

      const response = await OrganizationClient.update({ name: 'Acme' });

      expect(response).toEqual({ organization });
    });

    it('sends only the name, even when given extra keys', async () => {
      spyOn(ApiClient, 'patchJson').and.resolveTo({ organization });

      await OrganizationClient.update({ name: 'Acme', id: 7, createdAt: 'now' });

      expect(ApiClient.patchJson).toHaveBeenCalledWith('/organization.json', { name: 'Acme' });
    });
  });
});
