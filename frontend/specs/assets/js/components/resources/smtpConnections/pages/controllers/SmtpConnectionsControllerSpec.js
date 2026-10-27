import SmtpConnectionsController, {
  DELETE_ACTIVE_CONFIRM_MESSAGE, DELETE_CONFIRM_MESSAGE,
} from '../../../../../../../../assets/js/components/resources/smtpConnections/pages/controllers/SmtpConnectionsController.js';
import ApiError from '../../../../../../../../assets/js/client/ApiError.js';
import { installFakeWindow, uninstallFakeWindow } from '../../../../../../../support/fakeWindow.js';

describe('SmtpConnectionsController', () => {
  let setConnections;
  let setLoadState;
  let setListError;
  let client;
  let confirm;
  let controller;

  const first = { id: 1, name: 'Mailpit', active: true };
  const second = { id: 2, name: 'Relay', active: false };

  const applyUpdater = (current) => setConnections.calls.mostRecent().args[0](current);

  beforeEach(() => {
    setConnections = jasmine.createSpy('setConnections');
    setLoadState = jasmine.createSpy('setLoadState');
    setListError = jasmine.createSpy('setListError');
    client = jasmine.createSpyObj('client', ['list', 'activate', 'remove']);
    confirm = jasmine.createSpy('confirm').and.returnValue(true);
    controller = new SmtpConnectionsController({
      setConnections, setLoadState, setListError, client, confirm,
    });
  });

  describe('#load', () => {
    it('stores the listed connections and ends loading', async () => {
      client.list.and.resolveTo({ smtpConnections: [first, second] });

      await controller.load();

      expect(setConnections).toHaveBeenCalledWith([first, second]);
      expect(setLoadState).toHaveBeenCalledWith({ loading: false, loadError: null });
    });

    it('stores the load error on failure', async () => {
      client.list.and.rejectWith(new ApiError(500, 'Server error'));

      await controller.load();

      expect(setConnections).not.toHaveBeenCalled();
      expect(setLoadState).toHaveBeenCalledWith({ loading: false, loadError: 'Server error' });
    });

    it('stays idle, without an error, when the session ended', async () => {
      client.list.and.resolveTo(undefined);

      await controller.load();

      expect(setConnections).not.toHaveBeenCalled();
      expect(setLoadState).toHaveBeenCalledWith({ loading: false, loadError: null });
    });
  });

  describe('#handleActivate', () => {
    it('makes the returned connection the only active one', async () => {
      const activated = { ...second, active: true };
      client.activate.and.resolveTo({ smtpConnection: activated });

      await controller.handleActivate(2);

      expect(client.activate).toHaveBeenCalledWith(2);
      expect(setListError).toHaveBeenCalledWith(null);
      expect(applyUpdater([first, second])).toEqual([{ ...first, active: false }, activated]);
    });

    it('shows the error as a list error on failure', async () => {
      client.activate.and.rejectWith(new ApiError(404, 'SMTP connection not found', 'NOT_FOUND'));

      await controller.handleActivate(2);

      expect(setConnections).not.toHaveBeenCalled();
      expect(setListError).toHaveBeenCalledWith('SMTP connection not found');
    });

    it('changes nothing when the session ended', async () => {
      client.activate.and.resolveTo(undefined);

      await controller.handleActivate(2);

      expect(setConnections).not.toHaveBeenCalled();
    });
  });

  describe('#handleDelete', () => {
    it('asks for confirmation with the plain message on an inactive connection', async () => {
      client.remove.and.resolveTo({});

      await controller.handleDelete(second);

      expect(confirm).toHaveBeenCalledWith(DELETE_CONFIRM_MESSAGE);
    });

    it('warns that sending stops when deleting the active connection', async () => {
      client.remove.and.resolveTo({});

      await controller.handleDelete(first);

      expect(confirm).toHaveBeenCalledWith(DELETE_ACTIVE_CONFIRM_MESSAGE);
      expect(DELETE_ACTIVE_CONFIRM_MESSAGE).toContain('409 NO_SMTP_CONNECTION');
    });

    it('sends nothing when the confirmation is declined', async () => {
      confirm.and.returnValue(false);

      await controller.handleDelete(second);

      expect(client.remove).not.toHaveBeenCalled();
      expect(setConnections).not.toHaveBeenCalled();
    });

    it('removes the row once deleted', async () => {
      client.remove.and.resolveTo({});

      await controller.handleDelete(second);

      expect(client.remove).toHaveBeenCalledWith(2);
      expect(applyUpdater([first, second])).toEqual([first]);
    });

    it('shows the error as a list error on failure', async () => {
      client.remove.and.rejectWith(new ApiError(500, 'Server error'));

      await controller.handleDelete(second);

      expect(setConnections).not.toHaveBeenCalled();
      expect(setListError).toHaveBeenCalledWith('Server error');
    });

    it('keeps the row when the session ended', async () => {
      client.remove.and.resolveTo(undefined);

      await controller.handleDelete(second);

      expect(setConnections).not.toHaveBeenCalled();
    });
  });

  describe('default confirm', () => {
    afterEach(() => uninstallFakeWindow());

    it('defaults to window.confirm', async () => {
      const fakeConfirm = jasmine.createSpy('confirm').and.returnValue(false);
      installFakeWindow({ confirm: fakeConfirm });
      const defaulted = new SmtpConnectionsController({ setConnections, setLoadState, setListError, client });

      await defaulted.handleDelete(second);

      expect(fakeConfirm).toHaveBeenCalledWith(DELETE_CONFIRM_MESSAGE);
      expect(client.remove).not.toHaveBeenCalled();
    });
  });
});
