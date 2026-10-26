import OrganizationHelper from '../../../../../../../../assets/js/components/resources/organization/pages/helpers/OrganizationHelper.jsx';
import { renderedOutput } from '../../../../../../../support/renderedOutput.js';

describe('OrganizationHelper', () => {
  const buildHandlers = () => ({
    onSubmit: jasmine.createSpy('onSubmit'),
    onChange: jasmine.createSpy('onChange').and.returnValue(jasmine.createSpy('handler')),
  });
  const buildState = (overrides = {}) => ({
    name: 'Acme',
    loading: false,
    loadError: null,
    fieldErrors: {},
    submitError: null,
    success: false,
    ...overrides,
  });

  const renderPage = (state, handlers = buildHandlers()) => renderedOutput(
    OrganizationHelper.render(state, handlers),
  );

  describe('.render', () => {
    it('renders the page heading', () => {
      expect(renderPage(buildState()).containsElement('h1', 'Organization')).toBeTrue();
    });

    it('renders only a loading indicator while loading', () => {
      const page = renderPage(buildState({ loading: true }));

      expect(page.contains('spinner-border')).withContext('spinner').toBeTrue();
      expect(page.containsTag('form')).withContext('form').toBeFalse();
    });

    it('renders the load error alert instead of the form', () => {
      const page = renderPage(buildState({ loadError: 'network error' }));

      expect(page.contains('network error')).withContext('message').toBeTrue();
      expect(page.contains('alert-danger')).withContext('alert').toBeTrue();
      expect(page.containsTag('form')).withContext('form').toBeFalse();
    });

    it('renders the form with the organization name field and a Save button', () => {
      const handlers = buildHandlers();
      const page = renderPage(buildState(), handlers);

      expect(page.containsTag('form')).withContext('form').toBeTrue();
      expect(page.contains('Organization name')).withContext('label').toBeTrue();
      expect(page.containsAttribute('value', 'Acme')).withContext('value').toBeTrue();
      expect(page.containsAttribute('id', 'organization-name')).withContext('id').toBeTrue();
      expect(page.containsElement('button', 'Save')).withContext('button').toBeTrue();
      expect(handlers.onChange).toHaveBeenCalledWith('name');
    });

    it('renders the field error', () => {
      const page = renderPage(buildState({ fieldErrors: { name: 'Organization name is required' } }));

      expect(page.contains('is-invalid')).withContext('invalid class').toBeTrue();
      expect(page.contains('Organization name is required')).withContext('message').toBeTrue();
    });

    it('renders the submit error alert', () => {
      const page = renderPage(buildState({ submitError: 'server error' }));

      expect(page.contains('server error')).withContext('message').toBeTrue();
      expect(page.contains('alert-danger')).withContext('alert').toBeTrue();
    });

    it('renders the success alert', () => {
      const page = renderPage(buildState({ success: true }));

      expect(page.contains('Organization updated.')).withContext('message').toBeTrue();
      expect(page.contains('alert-success')).withContext('alert').toBeTrue();
    });

    it('renders no alert by default', () => {
      const page = renderPage(buildState());

      expect(page.contains('alert')).toBeFalse();
    });
  });
});
