import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import ApiTokenNew from '../../../../../../../assets/js/components/resources/apiTokens/pages/ApiTokenNew.jsx';
import ApiTokenNewHelper from '../../../../../../../assets/js/components/resources/apiTokens/pages/helpers/ApiTokenNewHelper.jsx';
import ApiTokenNewController, {
  COPY_IDLE,
} from '../../../../../../../assets/js/components/resources/apiTokens/pages/controllers/ApiTokenNewController.js';
import { renderCapturingHandlers } from '../../../../../../support/renderCapturingHandlers.js';
import { renderedOutput } from '../../../../../../support/renderedOutput.js';

describe('ApiTokenNew', () => {
  const defaultState = {
    name: '',
    scopes: ['send'],
    fieldErrors: {},
    submitError: null,
    submitting: false,
    createdToken: null,
    copyState: COPY_IDLE,
  };

  it('passes the default state, with send checked and no token, to the helper', () => {
    spyOn(ApiTokenNewHelper, 'render').and.returnValue(React.createElement('div', null, 'new token'));

    const page = renderedOutput(React.createElement(ApiTokenNew));

    expect(page.contains('new token')).toBeTrue();
    expect(ApiTokenNewHelper.render).toHaveBeenCalledWith(defaultState, {
      onSubmit: jasmine.any(Function),
      onNameChange: jasmine.any(Function),
      onToggleScope: jasmine.any(Function),
      onCopy: jasmine.any(Function),
    });
  });

  it('renders the form with send checked by default', () => {
    const page = renderedOutput(React.createElement(ApiTokenNew));

    expect(page.containsTag('form')).withContext('form').toBeTrue();
    expect(page.contains('checked=""')).withContext('checked').toBeTrue();
  });

  it('submits the fields, preventing default navigation', async () => {
    spyOn(ApiTokenNewController.prototype, 'handleSubmit').and.resolveTo();
    const handlers = renderCapturingHandlers(ApiTokenNew, ApiTokenNewHelper);
    const fakeEvent = { preventDefault: jasmine.createSpy('preventDefault') };

    await handlers.onSubmit(fakeEvent);

    expect(fakeEvent.preventDefault).toHaveBeenCalled();
    expect(ApiTokenNewController.prototype.handleSubmit).toHaveBeenCalledWith({ name: '', scopes: ['send'] });
  });

  it('delegates scope toggling and copying to the controller', () => {
    spyOn(ApiTokenNewController.prototype, 'toggleScope');
    spyOn(ApiTokenNewController.prototype, 'handleCopy').and.resolveTo();
    const handlers = renderCapturingHandlers(ApiTokenNew, ApiTokenNewHelper);

    handlers.onToggleScope('send')();
    handlers.onCopy();

    expect(ApiTokenNewController.prototype.toggleScope).toHaveBeenCalledWith('send');
    expect(ApiTokenNewController.prototype.handleCopy).toHaveBeenCalledWith(null);
  });

  it('updates the name locally on change, without reaching the controller', () => {
    spyOn(ApiTokenNewController.prototype, 'handleSubmit').and.resolveTo();
    const handlers = renderCapturingHandlers(ApiTokenNew, ApiTokenNewHelper);

    expect(() => handlers.onNameChange({ target: { value: 'Shop' } })).not.toThrow();
    expect(ApiTokenNewController.prototype.handleSubmit).not.toHaveBeenCalled();
  });

  it('starts without a token on every mount, so leaving the page drops it', () => {
    spyOn(ApiTokenNewHelper, 'render').and.returnValue(React.createElement('div'));

    renderToStaticMarkup(React.createElement(ApiTokenNew));
    renderToStaticMarkup(React.createElement(ApiTokenNew));

    ApiTokenNewHelper.render.calls.allArgs().forEach(([state]) => {
      expect(state.createdToken).toBeNull();
    });
  });
});
