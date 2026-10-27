import ApiTokensHelper from '../../../../../../../../assets/js/components/resources/apiTokens/pages/helpers/ApiTokensHelper.jsx';
import { renderedOutput } from '../../../../../../../support/renderedOutput.js';

describe('ApiTokensHelper', () => {
  const first = {
    id: 1, name: 'First', tokenPrefix: 'pdg_', scopes: ['send'],
    createdAt: '2026-10-17T12:00:00.000Z', lastUsedAt: null, revokedAt: null,
  };
  const second = {
    id: 2, name: 'Second', tokenPrefix: 'pdg_', scopes: ['send'],
    createdAt: '2026-10-18T12:00:00.000Z', lastUsedAt: null, revokedAt: '2026-10-19T12:00:00.000Z',
  };

  const buildHandlers = () => ({
    onRevoke: jasmine.createSpy('onRevoke').and.returnValue(jasmine.createSpy('handler')),
  });
  const buildState = (overrides = {}) => ({
    tokens: [], loading: false, loadError: null, listError: null, ...overrides,
  });
  const renderPage = (state, handlers = buildHandlers()) => renderedOutput(
    ApiTokensHelper.render(state, handlers),
  );

  describe('.render', () => {
    it('renders the heading and the New token button', () => {
      const page = renderPage(buildState());

      expect(page.containsElement('h1', 'API tokens')).withContext('heading').toBeTrue();
      expect(page.containsAttribute('href', '#/api-tokens/new')).withContext('href').toBeTrue();
      expect(page.containsElement('a', 'New token')).withContext('label').toBeTrue();
    });

    it('renders a loading indicator while loading', () => {
      const page = renderPage(buildState({ loading: true }));

      expect(page.contains('spinner-border')).withContext('spinner').toBeTrue();
      expect(page.containsTag('table')).withContext('table').toBeFalse();
    });

    it('renders the load error', () => {
      const page = renderPage(buildState({ loadError: 'Server error' }));

      expect(page.containsElement('div', 'Server error')).withContext('message').toBeTrue();
      expect(page.containsTag('table')).withContext('table').toBeFalse();
    });

    it('renders the empty state when there is no token', () => {
      const page = renderPage(buildState());

      expect(page.containsElement('p', 'No API tokens yet.')).withContext('empty').toBeTrue();
      expect(page.containsTag('table')).withContext('table').toBeFalse();
    });

    it('renders the list error', () => {
      const page = renderPage(buildState({ tokens: [first], listError: 'This API token no longer exists.' }));

      expect(page.containsElement('div', 'This API token no longer exists.')).toBeTrue();
    });

    it('renders a row per token, in the given order', () => {
      const page = renderPage(buildState({ tokens: [first, second] }));

      expect(page.containsTag('table')).withContext('table').toBeTrue();
      expect(page.containsInOrder('>First<', '>Second<')).withContext('order').toBeTrue();
    });

    it('binds the Revoke action of each row to its token', () => {
      const handlers = buildHandlers();

      renderPage(buildState({ tokens: [first, second] }), handlers);

      expect(handlers.onRevoke).toHaveBeenCalledWith(first);
      expect(handlers.onRevoke).toHaveBeenCalledWith(second);
    });
  });
});
