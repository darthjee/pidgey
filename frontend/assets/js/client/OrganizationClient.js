import ApiClient from './ApiClient.js';

const ORGANIZATION_PATH = '/organization.json';

/**
 * HTTP client for the caller's own organization. The organization is always the one the
 * logged-in user belongs to, so there is no id in the path. A `401` is recovered (or ends the
 * session) by {@link ApiClient}, in which case the methods resolve to `undefined`.
 */
const OrganizationClient = {
  /**
   * Fetch the caller's organization.
   *
   * @returns {Promise<{organization: {id: number, name: string, createdAt: string,
   *   updatedAt: string}}>} The organization; `undefined` when the session turned out to be
   *   expired.
   */
  async get() {
    return ApiClient.getJson(ORGANIZATION_PATH);
  },

  /**
   * Rename the caller's organization. Only `name` is sent.
   *
   * @param {{name: string}} attributes - The new attributes.
   * @param {string} attributes.name - The new organization name (trimmed by the API,
   *   1-255 characters).
   * @returns {Promise<{organization: {id: number, name: string, createdAt: string,
   *   updatedAt: string}}>} The organization, reloaded after the update; `undefined` when the
   *   session turned out to be expired.
   */
  async update({ name }) {
    return ApiClient.patchJson(ORGANIZATION_PATH, { name });
  },
};

export default OrganizationClient;
