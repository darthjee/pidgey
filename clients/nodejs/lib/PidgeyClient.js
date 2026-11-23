import { resolveConfig } from './config.js';

/**
 * Client for the Pidgey `/v1` API.
 *
 * The configuration lives in private fields, so the token never shows up in
 * `console.log(client)`, `JSON.stringify(client)` or a logger's serializer.
 */
export class PidgeyClient {
  /** @type {Readonly<import('./config.js').ClientConfig>} */
  #config;

  /**
   * Builds a PidgeyClient.
   * @param {object} [options] - Client options.
   * @param {string} [options.token] - The API token (required).
   * @param {string} [options.baseUrl] - Absolute `http(s)` URL of the Pidgey server (required).
   * @param {boolean} [options.allowInsecureHttp] - Allow `http://` on a non-loopback host (default `false`).
   * @param {number} [options.maxRetries] - Retries after the first attempt (default `2`).
   * @param {number} [options.retryDelayMs] - Base backoff delay in milliseconds (default `500`).
   * @param {boolean} [options.retryAmbiguous] - Retry failures that may have delivered (default `false`).
   * @param {number} [options.timeoutMs] - Per-attempt timeout in milliseconds (default `30000`).
   * @throws {import('./PidgeyError.js').PidgeyError} `CLIENT_CONFIGURATION` when an option is invalid.
   */
  constructor(options = {}) {
    this.#config = resolveConfig(options);
  }
}
