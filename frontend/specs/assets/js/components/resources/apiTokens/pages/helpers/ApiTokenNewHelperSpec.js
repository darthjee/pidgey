import ApiTokenNewHelper from '../../../../../../../../assets/js/components/resources/apiTokens/pages/helpers/ApiTokenNewHelper.jsx';
import ApiTokenCreatedPanel from '../../../../../../../../assets/js/components/resources/apiTokens/pages/tokens/ApiTokenCreatedPanel.jsx';
import { COPY_IDLE } from '../../../../../../../../assets/js/components/resources/apiTokens/pages/controllers/ApiTokenNewController.js';
import { renderedOutput } from '../../../../../../../support/renderedOutput.js';

describe('ApiTokenNewHelper', () => {
  const buildHandlers = () => ({
    onSubmit: jasmine.createSpy('onSubmit'),
    onNameChange: jasmine.createSpy('onNameChange'),
    onToggleScope: jasmine.createSpy('onToggleScope').and.returnValue(jasmine.createSpy('handler')),
    onCopy: jasmine.createSpy('onCopy'),
  });
  const buildState = (overrides = {}) => ({
    name: '',
    scopes: ['send'],
    fieldErrors: {},
    submitError: null,
    submitting: false,
    createdToken: null,
    copyState: COPY_IDLE,
    ...overrides,
  });
  const renderPage = (state, handlers = buildHandlers()) => renderedOutput(
    ApiTokenNewHelper.render(state, handlers),
  );

  describe('.render', () => {
    it('renders the heading and the form', () => {
      const page = renderPage(buildState());

      expect(page.containsElement('h1', 'New API token')).withContext('heading').toBeTrue();
      expect(page.containsTag('form')).withContext('form').toBeTrue();
      expect(page.containsElement('label', 'Name')).withContext('name').toBeTrue();
      expect(page.containsElement('legend', 'Scopes')).withContext('scopes').toBeTrue();
      expect(page.containsElement('button', 'Create')).withContext('create').toBeTrue();
      expect(page.containsElement('a', 'Cancel')).withContext('cancel').toBeTrue();
      expect(page.containsAttribute('href', '#/api-tokens')).withContext('cancel href').toBeTrue();
    });

    it('renders one checkbox per scope, checked when selected', () => {
      expect(renderPage(buildState()).contains('checked=""')).withContext('checked').toBeTrue();
      expect(renderPage(buildState({ scopes: [] })).contains('checked=""')).withContext('unchecked').toBeFalse();
      expect(renderPage(buildState()).containsElement('label', 'send')).withContext('label').toBeTrue();
    });

    it('binds each checkbox to its scope', () => {
      const handlers = buildHandlers();

      renderPage(buildState(), handlers);

      expect(handlers.onToggleScope).toHaveBeenCalledWith('send');
    });

    it('renders the field errors', () => {
      const page = renderPage(buildState({
        fieldErrors: { name: 'Name is required', scopes: 'Select at least one scope' },
      }));

      expect(page.containsElement('div', 'Name is required')).withContext('name').toBeTrue();
      expect(page.containsElement('div', 'Select at least one scope')).withContext('scopes').toBeTrue();
    });

    it('renders the submit error', () => {
      expect(renderPage(buildState({ submitError: 'Server error' })).containsElement('div', 'Server error')).toBeTrue();
    });

    it('disables Create while submitting', () => {
      expect(renderPage(buildState({ submitting: true })).contains('disabled=""')).withContext('disabled').toBeTrue();
      expect(renderPage(buildState()).contains('disabled=""')).withContext('enabled').toBeFalse();
    });

    it('replaces the form with the token panel once created', () => {
      const handlers = buildHandlers();
      const element = ApiTokenNewHelper.render(buildState({ createdToken: 'pdg_secret' }), handlers);
      const panel = element.props.children[1];

      expect(panel.type).toBe(ApiTokenCreatedPanel);
      expect(panel.props).toEqual({ token: 'pdg_secret', copyState: COPY_IDLE, onCopy: handlers.onCopy });
      expect(renderPage(buildState({ createdToken: 'pdg_secret' })).containsTag('form')).toBeFalse();
    });
  });
});
