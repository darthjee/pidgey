import React from 'react';
import Organization, { buildFieldUpdate, buildLoadEffect } from '../../../../../../../assets/js/components/resources/organization/pages/Organization.jsx';
import OrganizationHelper from '../../../../../../../assets/js/components/resources/organization/pages/helpers/OrganizationHelper.jsx';
import OrganizationController from '../../../../../../../assets/js/components/resources/organization/pages/controllers/OrganizationController.js';
import { renderCapturingHandlers } from '../../../../../../support/renderCapturingHandlers.js';
import { renderedOutput } from '../../../../../../support/renderedOutput.js';

describe('Organization', () => {
  beforeEach(() => {
    spyOn(OrganizationController.prototype, 'load').and.resolveTo();
  });

  it('passes the default state to the helper', () => {
    spyOn(OrganizationHelper, 'render').and.returnValue(React.createElement('div', null, 'organization'));

    const page = renderedOutput(React.createElement(Organization));

    expect(page.contains('organization')).toBeTrue();
    expect(OrganizationHelper.render).toHaveBeenCalledWith(
      {
        name: '', loading: true, loadError: null, fieldErrors: {}, submitError: null, success: false,
      },
      { onSubmit: jasmine.any(Function), onChange: jasmine.any(Function) },
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

  describe('buildFieldUpdate', () => {
    it('sets the changed field, keeping the others', () => {
      expect(buildFieldUpdate('name', 'Acme')({ name: 'Old', other: 1 })).toEqual({ name: 'Acme', other: 1 });
    });
  });

  it('delegates submission to the controller with the current fields, preventing default navigation', async () => {
    spyOn(OrganizationController.prototype, 'handleSubmit').and.resolveTo();
    const handlers = renderCapturingHandlers(Organization, OrganizationHelper);
    const fakeEvent = { preventDefault: jasmine.createSpy('preventDefault') };
    await handlers.onSubmit(fakeEvent);

    expect(fakeEvent.preventDefault).toHaveBeenCalled();
    expect(OrganizationController.prototype.handleSubmit).toHaveBeenCalledWith({ name: '' });
  });

  it('updates a field locally on change, without reaching the controller', () => {
    spyOn(OrganizationController.prototype, 'handleSubmit').and.resolveTo();
    const handlers = renderCapturingHandlers(Organization, OrganizationHelper);

    expect(() => handlers.onChange('name')({ target: { value: 'Acme' } })).not.toThrow();
    expect(OrganizationController.prototype.handleSubmit).not.toHaveBeenCalled();
  });
});
