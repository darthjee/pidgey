import OrganizationController from '../../../../../../../../assets/js/components/resources/organization/pages/controllers/OrganizationController.js';
import ApiError from '../../../../../../../../assets/js/client/ApiError.js';

describe('OrganizationController', () => {
  let setFields;
  let setFieldErrors;
  let setLoadState;
  let setSubmitError;
  let setSuccess;
  let client;

  const organization = {
    id: 1, name: 'Acme', createdAt: '2026-01-01T00:00:00.000Z', updatedAt: '2026-01-02T00:00:00.000Z',
  };

  beforeEach(() => {
    setFields = jasmine.createSpy('setFields');
    setFieldErrors = jasmine.createSpy('setFieldErrors');
    setLoadState = jasmine.createSpy('setLoadState');
    setSubmitError = jasmine.createSpy('setSubmitError');
    setSuccess = jasmine.createSpy('setSuccess');
    client = jasmine.createSpyObj('client', ['get', 'update']);
  });

  const buildController = () => new OrganizationController(
    setFields, setFieldErrors, setLoadState, setSubmitError, setSuccess, client,
  );

  describe('#load', () => {
    it('puts the organization name in the form and marks it loaded', async () => {
      client.get.and.resolveTo({ organization });

      await buildController().load();

      expect(setFields).toHaveBeenCalledWith({ name: 'Acme' });
      expect(setLoadState).toHaveBeenCalledWith({ loading: false, loadError: null });
    });

    it('leaves the screen idle, without an error, when the session ended', async () => {
      client.get.and.resolveTo(undefined);

      await buildController().load();

      expect(setFields).not.toHaveBeenCalled();
      expect(setLoadState).toHaveBeenCalledWith({ loading: false, loadError: null });
    });

    it('sets the load error when the request fails', async () => {
      client.get.and.rejectWith(new ApiError(500, 'boom'));

      await buildController().load();

      expect(setFields).not.toHaveBeenCalled();
      expect(setLoadState).toHaveBeenCalledWith({ loading: false, loadError: 'boom' });
    });
  });

  describe('#validate', () => {
    it('rejects an empty name', () => {
      expect(buildController().validate({ name: '' }))
        .toEqual({ name: 'Organization name is required' });
    });

    it('rejects a whitespace-only name', () => {
      expect(buildController().validate({ name: '   ' }))
        .toEqual({ name: 'Organization name is required' });
    });

    it('rejects a name over 255 characters', () => {
      expect(buildController().validate({ name: 'a'.repeat(256) }))
        .toEqual({ name: 'Organization name must be at most 255 characters' });
    });

    it('accepts a 255-character name', () => {
      expect(buildController().validate({ name: 'a'.repeat(255) })).toEqual({});
    });
  });

  describe('#handleSubmit', () => {
    it('clears the previous outcome before submitting', async () => {
      client.update.and.resolveTo({ organization });

      await buildController().handleSubmit({ name: 'Acme' });

      expect(setSuccess).toHaveBeenCalledWith(false);
      expect(setSubmitError).toHaveBeenCalledWith(null);
      expect(setFieldErrors).toHaveBeenCalledWith({});
    });

    it('sets the field errors and makes no API call when invalid', async () => {
      await buildController().handleSubmit({ name: '  ' });

      expect(setFieldErrors).toHaveBeenCalledWith({ name: 'Organization name is required' });
      expect(client.update).not.toHaveBeenCalled();
    });

    it('sends the trimmed name', async () => {
      client.update.and.resolveTo({ organization });

      await buildController().handleSubmit({ name: '  Acme  ' });

      expect(client.update).toHaveBeenCalledWith({ name: 'Acme' });
    });

    it('puts the returned name back and flags success', async () => {
      client.update.and.resolveTo({ organization: { ...organization, name: 'Acme Corp' } });

      await buildController().handleSubmit({ name: 'Acme Corp' });

      expect(setFields).toHaveBeenCalledWith({ name: 'Acme Corp' });
      expect(setSuccess).toHaveBeenCalledWith(true);
    });

    it('does nothing more when the session ended', async () => {
      client.update.and.resolveTo(undefined);

      await buildController().handleSubmit({ name: 'Acme' });

      expect(setFields).not.toHaveBeenCalled();
      expect(setSuccess).not.toHaveBeenCalledWith(true);
    });

    it('puts VALIDATION_FAILED details on the name field', async () => {
      client.update.and.rejectWith(new ApiError(400, 'invalid', 'VALIDATION_FAILED', ['name should not be empty']));

      await buildController().handleSubmit({ name: 'Acme' });

      expect(setFieldErrors).toHaveBeenCalledWith({ name: 'name should not be empty' });
      expect(setSubmitError).not.toHaveBeenCalledWith('invalid');
    });

    it('falls back to the error message when VALIDATION_FAILED has no details', async () => {
      client.update.and.rejectWith(new ApiError(400, 'invalid name', 'VALIDATION_FAILED'));

      await buildController().handleSubmit({ name: 'Acme' });

      expect(setFieldErrors).toHaveBeenCalledWith({ name: 'invalid name' });
    });

    it('turns any other ApiError into the submit error', async () => {
      client.update.and.rejectWith(new ApiError(500, 'server error', 'INTERNAL_ERROR'));

      await buildController().handleSubmit({ name: 'Acme' });

      expect(setSubmitError).toHaveBeenCalledWith('server error');
      expect(setSuccess).not.toHaveBeenCalledWith(true);
    });
  });
});
