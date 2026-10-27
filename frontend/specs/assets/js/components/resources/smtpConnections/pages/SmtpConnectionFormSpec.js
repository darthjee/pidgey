import React from 'react';
import SmtpConnectionForm, {
  buildFieldUpdate, buildLoadEffect,
} from '../../../../../../../assets/js/components/resources/smtpConnections/pages/SmtpConnectionForm.jsx';
import SmtpConnectionFormHelper from '../../../../../../../assets/js/components/resources/smtpConnections/pages/helpers/SmtpConnectionFormHelper.jsx';
import SmtpConnectionFormController from '../../../../../../../assets/js/components/resources/smtpConnections/pages/controllers/SmtpConnectionFormController.js';
import { renderCapturingHandlers } from '../../../../../../support/renderCapturingHandlers.js';
import { renderedOutput } from '../../../../../../support/renderedOutput.js';

describe('SmtpConnectionForm', () => {
  const emptyFields = {
    name: '', host: '', port: '', encryption: '', username: '', password: '', fromAddress: '',
  };
  const EditPage = () => React.createElement(SmtpConnectionForm, { id: '5' });

  beforeEach(() => {
    spyOn(SmtpConnectionFormController.prototype, 'load').and.resolveTo();
  });

  it('passes the create state to the helper', () => {
    spyOn(SmtpConnectionFormHelper, 'render').and.returnValue(React.createElement('div', null, 'form'));

    const page = renderedOutput(React.createElement(SmtpConnectionForm, {}));

    expect(page.contains('form')).toBeTrue();
    expect(SmtpConnectionFormHelper.render).toHaveBeenCalledWith(
      {
        mode: 'create', ...emptyFields, stored: null, loading: false, loadError: null, notFound: false,
        fieldErrors: {}, submitError: null, success: false,
      },
      {
        onSubmit: jasmine.any(Function), onChange: jasmine.any(Function), onRemoveCredentials: jasmine.any(Function),
      },
    );
  });

  it('starts the edit page loading', () => {
    spyOn(SmtpConnectionFormHelper, 'render').and.returnValue(React.createElement('div'));

    renderedOutput(React.createElement(EditPage));

    expect(SmtpConnectionFormHelper.render).toHaveBeenCalledWith(
      jasmine.objectContaining({ mode: 'edit', loading: true }),
      jasmine.any(Object),
    );
  });

  describe('buildLoadEffect', () => {
    it('loads the connection on edit', () => {
      const controller = jasmine.createSpyObj('controller', ['load']);

      buildLoadEffect(controller, '5')();

      expect(controller.load).toHaveBeenCalledWith('5');
    });

    it('loads nothing on create', () => {
      const controller = jasmine.createSpyObj('controller', ['load']);

      buildLoadEffect(controller, undefined)();

      expect(controller.load).not.toHaveBeenCalled();
    });
  });

  describe('buildFieldUpdate', () => {
    it('sets the changed field, keeping the others', () => {
      expect(buildFieldUpdate('host', 'mailpit')({ name: 'x', host: '' })).toEqual({ name: 'x', host: 'mailpit' });
    });
  });

  it('delegates submission with the id, fields and stored credentials, preventing default navigation', async () => {
    spyOn(SmtpConnectionFormController.prototype, 'handleSubmit').and.resolveTo();
    const handlers = renderCapturingHandlers(EditPage, SmtpConnectionFormHelper);
    const fakeEvent = { preventDefault: jasmine.createSpy('preventDefault') };

    await handlers.onSubmit(fakeEvent);

    expect(fakeEvent.preventDefault).toHaveBeenCalled();
    expect(SmtpConnectionFormController.prototype.handleSubmit).toHaveBeenCalledWith('5', emptyFields, null);
  });

  it('delegates Remove credentials with the id', async () => {
    spyOn(SmtpConnectionFormController.prototype, 'handleRemoveCredentials').and.resolveTo();
    const handlers = renderCapturingHandlers(EditPage, SmtpConnectionFormHelper);

    await handlers.onRemoveCredentials();

    expect(SmtpConnectionFormController.prototype.handleRemoveCredentials).toHaveBeenCalledWith('5');
  });

  it('updates a field locally on change, without reaching the controller', () => {
    spyOn(SmtpConnectionFormController.prototype, 'handleSubmit').and.resolveTo();
    const handlers = renderCapturingHandlers(EditPage, SmtpConnectionFormHelper);

    expect(() => handlers.onChange('name')({ target: { value: 'Relay' } })).not.toThrow();
    expect(SmtpConnectionFormController.prototype.handleSubmit).not.toHaveBeenCalled();
  });
});
