import ApiClient from './ApiClient.js';
import pickDefined from './pickDefined.js';

const COLLECTION_PATH = '/smtp-connections.json';

/**
 * Build the path of one SMTP connection, or of one of its actions.
 *
 * @param {number|string} id - The connection id.
 * @param {string} [action] - The action segment (`activate`, `test`); omitted for the item.
 * @returns {string} The request path.
 */
function itemPath(id, action) {
  return action === undefined
    ? `/smtp-connections/${id}.json`
    : `/smtp-connections/${id}/${action}.json`;
}

/**
 * Keep only the attributes the API accepts on create / update, dropping `undefined` values.
 * `active` and unknown keys are never sent; `''` is kept (it clears `username` / `password`).
 *
 * @param {object} attributes - The caller's attributes.
 * @returns {object} The request body.
 */
function buildBody(attributes) {
  const { name, host, port, encryption, username, password, fromAddress } = attributes;

  return pickDefined({ name, host, port, encryption, username, password, fromAddress });
}

/**
 * HTTP client for the caller's organization SMTP connections. A `401` is recovered (or ends
 * the session) by {@link ApiClient}, in which case the methods resolve to `undefined`.
 */
const SmtpConnectionsClient = {
  /**
   * List the organization's SMTP connections.
   *
   * @returns {Promise<{smtpConnections: object[]}>} The connections; `undefined` when the
   *   session turned out to be expired.
   */
  async list() {
    return ApiClient.getJson(COLLECTION_PATH);
  },

  /**
   * Create an SMTP connection (always created inactive).
   *
   * @param {object} attributes - The connection attributes (`name`, `host`, `port`,
   *   `encryption`, `username`, `password`, `fromAddress`); undefined keys are not sent.
   * @returns {Promise<{smtpConnection: object}>} The created connection; `undefined` when the
   *   session turned out to be expired.
   */
  async create(attributes) {
    return ApiClient.postJson(COLLECTION_PATH, buildBody(attributes));
  },

  /**
   * Fetch one SMTP connection.
   *
   * @param {number|string} id - The connection id.
   * @returns {Promise<{smtpConnection: object}>} The connection; `undefined` when the session
   *   turned out to be expired.
   */
  async get(id) {
    return ApiClient.getJson(itemPath(id));
  },

  /**
   * Update one SMTP connection. Only the given accepted keys are sent; `''` clears
   * `username` / `password`.
   *
   * @param {number|string} id - The connection id.
   * @param {object} attributes - The attributes to change.
   * @returns {Promise<{smtpConnection: object}>} The connection, reloaded after the update;
   *   `undefined` when the session turned out to be expired.
   */
  async update(id, attributes) {
    return ApiClient.patchJson(itemPath(id), buildBody(attributes));
  },

  /**
   * Delete one SMTP connection.
   *
   * @param {number|string} id - The connection id.
   * @returns {Promise<object>} The (empty) parsed response; `undefined` when the session
   *   turned out to be expired.
   */
  async remove(id) {
    return ApiClient.deleteJson(itemPath(id));
  },

  /**
   * Make one SMTP connection the organization's only active one. Sends no body.
   *
   * @param {number|string} id - The connection id.
   * @returns {Promise<{smtpConnection: object}>} The activated connection; `undefined` when
   *   the session turned out to be expired.
   */
  async activate(id) {
    return ApiClient.postJson(itemPath(id, 'activate'));
  },

  /**
   * Send a test email through one SMTP connection.
   *
   * @param {number|string} id - The connection id.
   * @param {{to: string}} [options] - Test options.
   * @param {string} [options.to] - The recipient; sent only when a non-empty string, otherwise
   *   the API uses the caller's own email.
   * @returns {Promise<{status: string, smtp: {code: number, message: string}}>} The send
   *   result; `undefined` when the session turned out to be expired.
   */
  async test(id, { to } = {}) {
    const body = typeof to === 'string' && to !== '' ? { to } : {};

    return ApiClient.postJson(itemPath(id, 'test'), body);
  },
};

export default SmtpConnectionsClient;
