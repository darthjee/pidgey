import ApiTokensController, {
  NOT_FOUND_MESSAGE, REVOKE_CONFIRM_MESSAGE,
} from '../../../../../../../../assets/js/components/resources/apiTokens/pages/controllers/ApiTokensController.js';
import ApiError from '../../../../../../../../assets/js/client/ApiError.js';
import { installFakeWindow, uninstallFakeWindow } from '../../../../../../../support/fakeWindow.js';

describe('ApiTokensController', () => {
  let setTokens;
  let setLoadState;
  let setListError;
  let client;
  let confirm;
  let controller;

  const active = { id: 1, name: 'Shop', revokedAt: null };
  const revoked = { id: 2, name: 'Old', revokedAt: '2026-10-20T09:30:00.000Z' };

  beforeEach(() => {
    setTokens = jasmine.createSpy('setTokens');
    setLoadState = jasmine.createSpy('setLoadState');
    setListError = jasmine.createSpy('setListError');
    client = jasmine.createSpyObj('client', ['list', 'revoke']);
    confirm = jasmine.createSpy('confirm').and.returnValue(true);
    controller = new ApiTokensController({
      setTokens, setLoadState, setListError, client, confirm,
    });
  });

  describe('#load', () => {
    it('stores the listed tokens, in the API order, and ends loading', async () => {
      client.list.and.resolveTo({ apiTokens: [active, revoked] });

      await controller.load();

      expect(setTokens).toHaveBeenCalledWith([active, revoked]);
      expect(setLoadState).toHaveBeenCalledWith({ loading: false, loadError: null });
    });

    it('stores an empty list', async () => {
      client.list.and.resolveTo({ apiTokens: [] });

      await controller.load();

      expect(setTokens).toHaveBeenCalledWith([]);
    });

    it('stores the load error on failure', async () => {
      client.list.and.rejectWith(new ApiError(500, 'Server error'));

      await controller.load();

      expect(setTokens).not.toHaveBeenCalled();
      expect(setLoadState).toHaveBeenCalledWith({ loading: false, loadError: 'Server error' });
    });

    it('stays idle, without an error, when the session ended', async () => {
      client.list.and.resolveTo(undefined);

      await controller.load();

      expect(setTokens).not.toHaveBeenCalled();
      expect(setLoadState).toHaveBeenCalledWith({ loading: false, loadError: null });
    });
  });

  describe('#handleRevoke', () => {
    beforeEach(() => {
      client.list.and.resolveTo({ apiTokens: [active, revoked] });
    });

    it('asks for confirmation', async () => {
      client.revoke.and.resolveTo({});

      await controller.handleRevoke(active);

      expect(confirm).toHaveBeenCalledWith(REVOKE_CONFIRM_MESSAGE);
    });

    it('sends nothing when the confirmation is declined', async () => {
      confirm.and.returnValue(false);

      await controller.handleRevoke(active);

      expect(client.revoke).not.toHaveBeenCalled();
      expect(client.list).not.toHaveBeenCalled();
      expect(setListError).not.toHaveBeenCalled();
    });

    it('revokes the token and reloads the list', async () => {
      client.revoke.and.resolveTo({});

      await controller.handleRevoke(active);

      expect(setListError).toHaveBeenCalledWith(null);
      expect(client.revoke).toHaveBeenCalledWith(1);
      expect(client.list).toHaveBeenCalled();
      expect(setTokens).toHaveBeenCalledWith([active, revoked]);
    });

    it('reloads and shows a message on a 404', async () => {
      client.revoke.and.rejectWith(new ApiError(404, 'API token not found', 'NOT_FOUND'));

      await controller.handleRevoke(active);

      expect(client.list).toHaveBeenCalled();
      expect(setTokens).toHaveBeenCalledWith([active, revoked]);
      expect(setListError.calls.mostRecent().args).toEqual([NOT_FOUND_MESSAGE]);
    });

    it('shows other errors as a list error, without reloading', async () => {
      client.revoke.and.rejectWith(new ApiError(500, 'Server error'));

      await controller.handleRevoke(active);

      expect(client.list).not.toHaveBeenCalled();
      expect(setListError).toHaveBeenCalledWith('Server error');
    });

    it('changes nothing when the session ended', async () => {
      client.revoke.and.resolveTo(undefined);

      await controller.handleRevoke(active);

      expect(client.list).not.toHaveBeenCalled();
      expect(setTokens).not.toHaveBeenCalled();
    });
  });

  describe('default confirm', () => {
    afterEach(() => uninstallFakeWindow());

    it('defaults to window.confirm', async () => {
      const fakeConfirm = jasmine.createSpy('confirm').and.returnValue(false);
      installFakeWindow({ confirm: fakeConfirm });
      const defaulted = new ApiTokensController({ setTokens, setLoadState, setListError, client });

      await defaulted.handleRevoke(active);

      expect(fakeConfirm).toHaveBeenCalledWith(REVOKE_CONFIRM_MESSAGE);
      expect(client.revoke).not.toHaveBeenCalled();
    });
  });
});
