import React from 'react';
import SmtpConnections, { buildLoadEffect } from '../../../../../../../assets/js/components/resources/smtpConnections/pages/SmtpConnections.jsx';
import SmtpConnectionsHelper from '../../../../../../../assets/js/components/resources/smtpConnections/pages/helpers/SmtpConnectionsHelper.jsx';
import SmtpConnectionsController from '../../../../../../../assets/js/components/resources/smtpConnections/pages/controllers/SmtpConnectionsController.js';
import SmtpConnectionTestController, {
  CLOSED_TEST_STATE,
} from '../../../../../../../assets/js/components/resources/smtpConnections/pages/controllers/SmtpConnectionTestController.js';
import { renderCapturingHandlers } from '../../../../../../support/renderCapturingHandlers.js';
import { renderedOutput } from '../../../../../../support/renderedOutput.js';

describe('SmtpConnections', () => {
  const connection = { id: 3, name: 'Relay', active: false };

  beforeEach(() => {
    spyOn(SmtpConnectionsController.prototype, 'load').and.resolveTo();
  });

  it('passes the default state to the helper', () => {
    spyOn(SmtpConnectionsHelper, 'render').and.returnValue(React.createElement('div', null, 'connections'));

    const page = renderedOutput(React.createElement(SmtpConnections));

    expect(page.contains('connections')).toBeTrue();
    expect(SmtpConnectionsHelper.render).toHaveBeenCalledWith(
      {
        connections: [], loading: true, loadError: null, listError: null, test: CLOSED_TEST_STATE,
      },
      jasmine.objectContaining({ onActivate: jasmine.any(Function), onSubmitTest: jasmine.any(Function) }),
    );
  });

  describe('buildLoadEffect', () => {
    it('triggers a load on the given controller', () => {
      const controller = jasmine.createSpyObj('controller', ['load']);
      controller.load.and.resolveTo();

      buildLoadEffect(controller)();

      expect(controller.load).toHaveBeenCalled();
    });
  });

  it('delegates activate and delete to the controller', () => {
    spyOn(SmtpConnectionsController.prototype, 'handleActivate').and.resolveTo();
    spyOn(SmtpConnectionsController.prototype, 'handleDelete').and.resolveTo();
    const handlers = renderCapturingHandlers(SmtpConnections, SmtpConnectionsHelper);

    handlers.onActivate(3)();
    handlers.onDelete(connection)();

    expect(SmtpConnectionsController.prototype.handleActivate).toHaveBeenCalledWith(3);
    expect(SmtpConnectionsController.prototype.handleDelete).toHaveBeenCalledWith(connection);
  });

  it('delegates opening and closing the test modal to the test controller', () => {
    spyOn(SmtpConnectionTestController.prototype, 'open');
    spyOn(SmtpConnectionTestController.prototype, 'close');
    const handlers = renderCapturingHandlers(SmtpConnections, SmtpConnectionsHelper);

    handlers.onOpenTest(connection)();
    handlers.onCloseTest();

    expect(SmtpConnectionTestController.prototype.open).toHaveBeenCalledWith(connection);
    expect(SmtpConnectionTestController.prototype.close).toHaveBeenCalled();
  });

  it('submits the test email with the modal state, preventing default navigation', async () => {
    spyOn(SmtpConnectionTestController.prototype, 'submit').and.resolveTo();
    const handlers = renderCapturingHandlers(SmtpConnections, SmtpConnectionsHelper);
    const fakeEvent = { preventDefault: jasmine.createSpy('preventDefault') };

    await handlers.onSubmitTest(fakeEvent);

    expect(fakeEvent.preventDefault).toHaveBeenCalled();
    expect(SmtpConnectionTestController.prototype.submit).toHaveBeenCalledWith(CLOSED_TEST_STATE);
  });

  it('updates the recipient locally on change, without reaching the controller', () => {
    spyOn(SmtpConnectionTestController.prototype, 'submit').and.resolveTo();
    const handlers = renderCapturingHandlers(SmtpConnections, SmtpConnectionsHelper);

    expect(() => handlers.onRecipientChange({ target: { value: 'bob@example.com' } })).not.toThrow();
    expect(SmtpConnectionTestController.prototype.submit).not.toHaveBeenCalled();
  });
});
