import React from 'react';
import ApiTokens, { buildLoadEffect } from '../../../../../../../assets/js/components/resources/apiTokens/pages/ApiTokens.jsx';
import ApiTokensHelper from '../../../../../../../assets/js/components/resources/apiTokens/pages/helpers/ApiTokensHelper.jsx';
import ApiTokensController from '../../../../../../../assets/js/components/resources/apiTokens/pages/controllers/ApiTokensController.js';
import { renderCapturingHandlers } from '../../../../../../support/renderCapturingHandlers.js';
import { renderedOutput } from '../../../../../../support/renderedOutput.js';

describe('ApiTokens', () => {
  const token = { id: 5, name: 'Shop backend', revokedAt: null };

  beforeEach(() => {
    spyOn(ApiTokensController.prototype, 'load').and.resolveTo();
  });

  it('passes the default state to the helper', () => {
    spyOn(ApiTokensHelper, 'render').and.returnValue(React.createElement('div', null, 'tokens'));

    const page = renderedOutput(React.createElement(ApiTokens));

    expect(page.contains('tokens')).toBeTrue();
    expect(ApiTokensHelper.render).toHaveBeenCalledWith(
      { tokens: [], loading: true, loadError: null, listError: null },
      { onRevoke: jasmine.any(Function) },
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

  it('delegates revoke to the controller', () => {
    spyOn(ApiTokensController.prototype, 'handleRevoke').and.resolveTo();
    const handlers = renderCapturingHandlers(ApiTokens, ApiTokensHelper);

    handlers.onRevoke(token)();

    expect(ApiTokensController.prototype.handleRevoke).toHaveBeenCalledWith(token);
  });
});
