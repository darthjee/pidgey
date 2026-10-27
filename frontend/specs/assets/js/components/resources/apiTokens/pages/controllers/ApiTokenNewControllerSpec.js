import ApiTokenNewController, {
  COPY_DONE, COPY_FAILED, COPY_IDLE,
} from '../../../../../../../../assets/js/components/resources/apiTokens/pages/controllers/ApiTokenNewController.js';
import ApiError from '../../../../../../../../assets/js/client/ApiError.js';
import { installFakeNavigator, uninstallFakeNavigator } from '../../../../../../../support/fakeNavigator.js';

describe('ApiTokenNewController', () => {
  const PLAIN_TOKEN = 'pdg_plain-secret-token';
  const created = {
    apiToken: {
      id: 5, name: 'Shop', tokenPrefix: 'pdg_', scopes: ['send'], token: PLAIN_TOKEN,
    },
  };

  let setters;
  let client;
  let clipboard;
  let controller;

  beforeEach(() => {
    setters = {
      setScopes: jasmine.createSpy('setScopes'),
      setFieldErrors: jasmine.createSpy('setFieldErrors'),
      setSubmitError: jasmine.createSpy('setSubmitError'),
      setSubmitting: jasmine.createSpy('setSubmitting'),
      setCreatedToken: jasmine.createSpy('setCreatedToken'),
      setCopyState: jasmine.createSpy('setCopyState'),
    };
    client = jasmine.createSpyObj('client', ['create']);
    clipboard = jasmine.createSpyObj('clipboard', ['writeText']);
    controller = new ApiTokenNewController({ ...setters, client, clipboard });
  });

  describe('#toggleScope', () => {
    const applyUpdater = (current) => setters.setScopes.calls.mostRecent().args[0](current);

    it('unselects a selected scope', () => {
      controller.toggleScope('send');

      expect(applyUpdater(['send'])).toEqual([]);
    });

    it('selects an unselected scope', () => {
      controller.toggleScope('send');

      expect(applyUpdater([])).toEqual(['send']);
    });
  });

  describe('#handleSubmit', () => {
    it('sends nothing and sets the errors when the name is blank', async () => {
      await controller.handleSubmit({ name: '   ', scopes: ['send'] });

      expect(client.create).not.toHaveBeenCalled();
      expect(setters.setFieldErrors).toHaveBeenCalledWith({ name: 'Name is required' });
    });

    it('sends nothing when the name is too long', async () => {
      await controller.handleSubmit({ name: 'a'.repeat(256), scopes: ['send'] });

      expect(client.create).not.toHaveBeenCalled();
      expect(setters.setFieldErrors).toHaveBeenCalledWith({ name: 'Name must be at most 255 characters' });
    });

    it('sends nothing when no scope is selected', async () => {
      await controller.handleSubmit({ name: 'Shop', scopes: [] });

      expect(client.create).not.toHaveBeenCalled();
      expect(setters.setFieldErrors).toHaveBeenCalledWith({ scopes: 'Select at least one scope' });
    });

    it('sends the trimmed name and the scopes, then hands the token to the panel', async () => {
      client.create.and.resolveTo(created);

      await controller.handleSubmit({ name: '  Shop  ', scopes: ['send'] });

      expect(setters.setFieldErrors).toHaveBeenCalledWith({});
      expect(setters.setSubmitError).toHaveBeenCalledWith(null);
      expect(client.create).toHaveBeenCalledWith({ name: 'Shop', scopes: ['send'] });
      expect(setters.setCreatedToken).toHaveBeenCalledWith(PLAIN_TOKEN);
      expect(setters.setCopyState).toHaveBeenCalledWith(COPY_IDLE);
    });

    it('flags the submission as in flight while sending', async () => {
      client.create.and.callFake(async () => {
        expect(setters.setSubmitting.calls.mostRecent().args).toEqual([true]);
        return created;
      });

      await controller.handleSubmit({ name: 'Shop', scopes: ['send'] });

      expect(setters.setSubmitting.calls.mostRecent().args).toEqual([false]);
    });

    it('changes nothing when the session ended', async () => {
      client.create.and.resolveTo(undefined);

      await controller.handleSubmit({ name: 'Shop', scopes: ['send'] });

      expect(setters.setCreatedToken).not.toHaveBeenCalled();
      expect(setters.setSubmitError).not.toHaveBeenCalledWith(jasmine.any(String));
    });

    it('maps VALIDATION_FAILED details to the fields', async () => {
      client.create.and.rejectWith(new ApiError(400, 'Validation failed', 'VALIDATION_FAILED', [
        'name should not be empty', 'scopes should not be empty',
      ]));

      await controller.handleSubmit({ name: 'Shop', scopes: ['send'] });

      expect(setters.setFieldErrors).toHaveBeenCalledWith({
        name: 'name should not be empty', scopes: 'scopes should not be empty',
      });
      expect(setters.setSubmitError).not.toHaveBeenCalledWith(jasmine.any(String));
      expect(setters.setCreatedToken).not.toHaveBeenCalled();
    });

    it('shows the unmatched VALIDATION_FAILED details as a submit error', async () => {
      client.create.and.rejectWith(new ApiError(400, 'Validation failed', 'VALIDATION_FAILED', [
        'property foo should not exist',
      ]));

      await controller.handleSubmit({ name: 'Shop', scopes: ['send'] });

      expect(setters.setSubmitError).toHaveBeenCalledWith('property foo should not exist');
    });

    it('shows other errors as a submit error', async () => {
      client.create.and.rejectWith(new ApiError(500, 'Server error'));

      await controller.handleSubmit({ name: 'Shop', scopes: ['send'] });

      expect(setters.setSubmitError).toHaveBeenCalledWith('Server error');
      expect(setters.setSubmitting.calls.mostRecent().args).toEqual([false]);
    });

    it('shows a VALIDATION_FAILED error without details as a submit error', async () => {
      client.create.and.rejectWith(new ApiError(400, 'Validation failed', 'VALIDATION_FAILED'));

      await controller.handleSubmit({ name: 'Shop', scopes: ['send'] });

      expect(setters.setSubmitError).toHaveBeenCalledWith('Validation failed');
    });
  });

  describe('#handleCopy', () => {
    it('writes the token to the clipboard', async () => {
      clipboard.writeText.and.resolveTo();

      await controller.handleCopy(PLAIN_TOKEN);

      expect(clipboard.writeText).toHaveBeenCalledWith(PLAIN_TOKEN);
      expect(setters.setCopyState).toHaveBeenCalledWith(COPY_DONE);
    });

    it('falls back when the clipboard rejects', async () => {
      clipboard.writeText.and.rejectWith(new Error('denied'));

      await controller.handleCopy(PLAIN_TOKEN);

      expect(setters.setCopyState).toHaveBeenCalledWith(COPY_FAILED);
    });

    describe('with the default clipboard', () => {
      afterEach(() => uninstallFakeNavigator());

      it('uses navigator.clipboard', async () => {
        const fake = jasmine.createSpyObj('clipboard', ['writeText']);
        fake.writeText.and.resolveTo();
        installFakeNavigator({ clipboard: fake });
        const defaulted = new ApiTokenNewController({ ...setters, client });

        await defaulted.handleCopy(PLAIN_TOKEN);

        expect(fake.writeText).toHaveBeenCalledWith(PLAIN_TOKEN);
        expect(setters.setCopyState).toHaveBeenCalledWith(COPY_DONE);
      });

      it('falls back when the clipboard is missing', async () => {
        installFakeNavigator({});
        const defaulted = new ApiTokenNewController({ ...setters, client });

        await defaulted.handleCopy(PLAIN_TOKEN);

        expect(setters.setCopyState).toHaveBeenCalledWith(COPY_FAILED);
      });
    });
  });

  describe('plain token handling', () => {
    const storageNames = ['localStorage', 'sessionStorage'];
    let previous;
    let storages;
    let consoleSpies;

    beforeEach(() => {
      previous = new Map(storageNames.map((name) => [name, Object.getOwnPropertyDescriptor(globalThis, name)]));
      storages = new Map(storageNames.map((name) => {
        const storage = jasmine.createSpyObj(name, ['setItem', 'getItem', 'removeItem', 'clear']);
        Object.defineProperty(globalThis, name, { value: storage, configurable: true, writable: true });
        return [name, storage];
      }));
      consoleSpies = ['log', 'info', 'debug', 'warn', 'error'].map((method) => spyOn(console, method));
    });

    afterEach(() => {
      previous.forEach((descriptor, name) => {
        if (descriptor) {
          Object.defineProperty(globalThis, name, descriptor);
        } else {
          delete globalThis[name];
        }
      });
    });

    it('never persists nor logs the token during create and copy', async () => {
      client.create.and.resolveTo(created);
      clipboard.writeText.and.rejectWith(new Error('denied'));

      await controller.handleSubmit({ name: 'Shop', scopes: ['send'] });
      await controller.handleCopy(PLAIN_TOKEN);

      storages.forEach((storage) => expect(storage.setItem).not.toHaveBeenCalled());
      consoleSpies.forEach((spy) => expect(spy).not.toHaveBeenCalled());
    });

    it('keeps no reference to the token on the controller', async () => {
      client.create.and.resolveTo(created);

      await controller.handleSubmit({ name: 'Shop', scopes: ['send'] });

      expect(JSON.stringify(Object.values(controller).filter((value) => typeof value === 'string')))
        .not.toContain(PLAIN_TOKEN);
    });
  });
});
