import LoginModalController from '../../../../../../../assets/js/components/common/loginModal/controllers/LoginModalController.js';
import ApiError from '../../../../../../../assets/js/client/ApiError.js';
import AuthStatus from '../../../../../../../assets/js/client/AuthStatus.js';
import LoginModalEvents from '../../../../../../../assets/js/client/LoginModalEvents.js';
import { installFakeWindow, uninstallFakeWindow } from '../../../../../../support/fakeWindow.js';

describe('LoginModalController #handleSubmit register mode', () => {
  let setFieldErrors;
  let setSubmitError;
  let client;

  const registerFields = {
    username: 'foo',
    email: 'foo@example.com',
    password: 'secret',
    passwordConfirmation: 'secret',
    organizationName: 'Acme',
  };

  const build = () => {
    const noop = jasmine.createSpy('noop');

    return new LoginModalController(
      noop, noop, setFieldErrors, setSubmitError, noop, noop, client,
    );
  };

  beforeEach(() => {
    setFieldErrors = jasmine.createSpy('setFieldErrors');
    setSubmitError = jasmine.createSpy('setSubmitError');
    client = jasmine.createSpyObj('client', ['register']);
    spyOn(AuthStatus, 'refresh').and.resolveTo();
    spyOn(LoginModalEvents, 'close');
    installFakeWindow({ location: { hash: '' } });
  });

  afterEach(() => {
    uninstallFakeWindow();
  });

  it('sets field errors and skips the API call when the form is invalid', async () => {
    await build().handleSubmit('register', { ...registerFields, email: 'nope' });

    expect(setFieldErrors).toHaveBeenCalledWith(jasmine.objectContaining({ email: jasmine.any(String) }));
    expect(client.register).not.toHaveBeenCalled();
  });

  [
    ['missing', undefined],
    ['empty', ''],
    ['blank', '   '],
  ].forEach(([label, organizationName]) => {
    it(`flags a ${label} organization name and skips the API call`, async () => {
      await build().handleSubmit('register', { ...registerFields, organizationName });

      expect(setFieldErrors).toHaveBeenCalledWith({
        organizationName: 'Organization name is required',
      });
      expect(client.register).not.toHaveBeenCalled();
    });
  });

  it('registers with the organization name and runs the shared success path once', async () => {
    client.register.and.resolveTo({ user: { id: 1, username: 'foo', isAdmin: false }, refreshToken: 't' });

    await build().handleSubmit('register', registerFields);

    expect(setFieldErrors).toHaveBeenCalledWith({});
    expect(client.register).toHaveBeenCalledWith(
      jasmine.objectContaining({ organizationName: 'Acme' }),
    );
    expect(client.register).toHaveBeenCalledWith(registerFields);
    expect(AuthStatus.refresh).toHaveBeenCalledOnceWith(client);
    expect(LoginModalEvents.close).toHaveBeenCalledTimes(1);
    expect(globalThis.window.location.hash).toBe('/');
  });

  it('sets a submit error when the register request fails', async () => {
    client.register.and.rejectWith(new Error('username is not available'));

    await build().handleSubmit('register', registerFields);

    expect(setSubmitError).toHaveBeenCalledWith('username is not available');
    expect(AuthStatus.refresh).not.toHaveBeenCalled();
  });

  it('sets the submit error to the ApiError message', async () => {
    client.register.and.rejectWith(new ApiError(400, 'Validation failed', 'VALIDATION_FAILED', {
      organizationName: 'is required',
    }));

    await build().handleSubmit('register', registerFields);

    expect(setSubmitError).toHaveBeenCalledWith('Validation failed');
    expect(setFieldErrors).not.toHaveBeenCalledWith(jasmine.objectContaining({
      organizationName: jasmine.anything(),
    }));
  });
});
