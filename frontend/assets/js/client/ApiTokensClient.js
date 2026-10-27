import ApiClient from './ApiClient.js';
import pickDefined from './pickDefined.js';

const COLLECTION_PATH = '/api-tokens.json';

/**
 * Build the path of one API token.
 *
 * @param {number|string} id - The token id.
 * @returns {string} The request path.
 */
function itemPath(id) {
  return `/api-tokens/${id}.json`;
}

/**
 * HTTP client for the caller's organization API tokens. A `401` is recovered (or ends the
 * session) by {@link ApiClient}, in which case the methods resolve to `undefined`. The client
 * never keeps any response (in particular the plain token returned on create).
 */
const ApiTokensClient = {
  /**
   * List the organization's API tokens, revoked ones included.
   *
   * @returns {Promise<{apiTokens: object[]}>} The tokens; `undefined` when the session turned
   *   out to be expired.
   */
  async list() {
    return ApiClient.getJson(COLLECTION_PATH);
  },

  /**
   * Create an API token. Only `name` and `scopes` are sent; undefined keys are dropped.
   *
   * @param {object} attributes - The token attributes.
   * @param {string} [attributes.name] - The token label.
   * @param {string[]} [attributes.scopes] - The scope names.
   * @returns {Promise<{apiToken: object}>} The created token, including the plain `token`
   *   (returned once); `undefined` when the session turned out to be expired.
   */
  async create({ name, scopes } = {}) {
    return ApiClient.postJson(COLLECTION_PATH, pickDefined({ name, scopes }));
  },

  /**
   * Revoke one API token. Sends no body.
   *
   * @param {number|string} id - The token id.
   * @returns {Promise<object>} The (empty) parsed response; `undefined` when the session
   *   turned out to be expired.
   */
  async revoke(id) {
    return ApiClient.deleteJson(itemPath(id));
  },
};

export default ApiTokensClient;
