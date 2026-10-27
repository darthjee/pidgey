import ApiTokensClient from '../../../../../client/ApiTokensClient.js';

/**
 * Confirmation text shown before revoking an API token.
 *
 * @type {string}
 */
export const REVOKE_CONFIRM_MESSAGE = 'Revoke this API token? Integrations using it stop working '
  + 'immediately. This cannot be undone.';

/**
 * List error shown when the token to revoke no longer exists (`404`).
 *
 * @type {string}
 */
export const NOT_FOUND_MESSAGE = 'This API token no longer exists.';

const defaultConfirm = (message) => window.confirm(message);

/**
 * Controller for the API tokens list page: loads the organization's tokens and revokes one.
 * Every method that reaches {@link ApiTokensClient} treats an `undefined` result the same way:
 * return without touching any further state — `ApiClient` already opened the login modal
 * itself on a session-expired `401`.
 */
export default class ApiTokensController {
  /**
   * Create an API tokens controller.
   *
   * @param {object} options - Controller dependencies.
   * @param {Function} options.setTokens - React state setter for the listed tokens.
   * @param {Function} options.setLoadState - React state setter for the load state,
   *   `{ loading: boolean, loadError: (string|null) }`.
   * @param {Function} options.setListError - React state setter for the error of the last
   *   row action (revoke).
   * @param {typeof ApiTokensClient} [options.client] - API tokens HTTP client override, for
   *   testability.
   * @param {Function} [options.confirm] - Confirmation dialog override (receives the message
   *   and returns a boolean), for testability; defaults to `window.confirm`.
   */
  constructor({
    setTokens, setLoadState, setListError,
    client = ApiTokensClient, confirm = defaultConfirm,
  }) {
    this.setTokens = setTokens;
    this.setLoadState = setLoadState;
    this.setListError = setListError;
    this.client = client;
    this.confirm = confirm;
  }

  /**
   * Load the organization's tokens, or store the load error on failure. When the session
   * ended (`undefined` result), the screen is left idle, without an error.
   *
   * @returns {Promise<void>} Resolves once the load finishes.
   */
  async load() {
    try {
      const result = await this.client.list();

      if (result) {
        this.setTokens(result.apiTokens);
      }

      this.setLoadState({ loading: false, loadError: null });
    } catch (error) {
      this.setLoadState({ loading: false, loadError: error.message });
    }
  }

  /**
   * Ask for confirmation, then revoke the token and reload the list (so `revokedAt` comes
   * from the API). A `404` reloads the list and shows {@link NOT_FOUND_MESSAGE}; other errors
   * are shown above the list. Cancelling sends nothing.
   *
   * @param {{id: number}} token - The row's token.
   * @returns {Promise<void>} Resolves once the request (and reload) finishes, or immediately
   *   when cancelled.
   */
  async handleRevoke(token) {
    if (!this.confirm(REVOKE_CONFIRM_MESSAGE)) {
      return;
    }

    this.setListError(null);

    try {
      const result = await this.client.revoke(token.id);

      if (!result) {
        return;
      }

      await this.load();
    } catch (error) {
      await this.handleRevokeError(error);
    }
  }

  /**
   * Show a revoke failure: a `404` reloads the list and shows {@link NOT_FOUND_MESSAGE}, any
   * other error shows its message.
   *
   * @param {Error} error - The revoke failure.
   * @returns {Promise<void>} Resolves once the error is shown (after the reload on a `404`).
   */
  async handleRevokeError(error) {
    if (error.status !== 404) {
      this.setListError(error.message);
      return;
    }

    await this.load();
    this.setListError(NOT_FOUND_MESSAGE);
  }
}
