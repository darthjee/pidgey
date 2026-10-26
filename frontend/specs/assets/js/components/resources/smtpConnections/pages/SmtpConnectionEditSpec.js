import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import SmtpConnectionEdit from '../../../../../../../assets/js/components/resources/smtpConnections/pages/SmtpConnectionEdit.jsx';
import SmtpConnectionFormHelper from '../../../../../../../assets/js/components/resources/smtpConnections/pages/helpers/SmtpConnectionFormHelper.jsx';
import SmtpConnectionFormController from '../../../../../../../assets/js/components/resources/smtpConnections/pages/controllers/SmtpConnectionFormController.js';
import { installFakeWindow, uninstallFakeWindow } from '../../../../../../support/fakeWindow.js';

describe('SmtpConnectionEdit', () => {
  let handlers;

  const render = () => {
    spyOn(SmtpConnectionFormHelper, 'render').and.callFake((_state, given) => {
      handlers = given;
      return React.createElement('div');
    });
    renderToStaticMarkup(React.createElement(SmtpConnectionEdit));
  };

  beforeEach(() => {
    spyOn(SmtpConnectionFormController.prototype, 'handleRemoveCredentials').and.resolveTo();
  });

  afterEach(() => uninstallFakeWindow());

  it('renders the form in edit mode for the route id', async () => {
    installFakeWindow({ location: { hash: '#/smtp-connections/42/edit' } });
    render();

    await handlers.onRemoveCredentials();

    expect(SmtpConnectionFormHelper.render).toHaveBeenCalledWith(jasmine.objectContaining({ mode: 'edit' }), jasmine.any(Object));
    expect(SmtpConnectionFormController.prototype.handleRemoveCredentials).toHaveBeenCalledWith('42');
  });

  it('falls back to create mode when the hash does not carry an id', () => {
    render();

    expect(SmtpConnectionFormHelper.render).toHaveBeenCalledWith(jasmine.objectContaining({ mode: 'create' }), jasmine.any(Object));
  });
});
