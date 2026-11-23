import { PidgeyError } from './PidgeyError.js';

/**
 * Resolved, validated client configuration.
 * @typedef {object} ClientConfig
 * @property {string} token - The API token.
 * @property {string} endpoint - Normalized `<baseUrl>/v1/messages` URL.
 * @property {boolean} allowInsecureHttp - Whether a cleartext non-loopback URL is allowed.
 * @property {number} maxRetries - Maximum number of retries after the first attempt.
 * @property {number} retryDelayMs - Base delay of the exponential backoff.
 * @property {boolean} retryAmbiguous - Whether ambiguous failures are retried.
 * @property {number} timeoutMs - Per-attempt timeout in milliseconds.
 */

/**
 * Throws a `CLIENT_CONFIGURATION` error.
 * @param {string} reason - What is wrong; it must never contain the token or the URL.
 * @returns {never} Always throws.
 */
function fail(reason) {
  throw new PidgeyError(reason, { code: 'CLIENT_CONFIGURATION', possiblySent: false });
}

/**
 * Tells whether a hostname, as normalized by WHATWG URL parsing, is a literal loopback host.
 *
 * Accepted: `localhost`, any `127.x.x.x` address and `[::1]`. Not accepted: `localhost.`,
 * `*.localhost`, the IPv4-mapped `[::ffff:7f00:1]` and `0.0.0.0`. There is no DNS lookup.
 * @param {string} hostname - The `hostname` of a parsed `URL`.
 * @returns {boolean} `true` for a loopback host.
 */
export function isLoopbackHost(hostname) {
  return hostname === 'localhost' || hostname === '[::1]' || /^127\.\d+\.\d+\.\d+$/.test(hostname);
}

/**
 * Parses `baseUrl` into the `/v1/messages` endpoint, enforcing the cleartext rule.
 * @param {unknown} baseUrl - The `baseUrl` option.
 * @param {boolean} allowInsecureHttp - The resolved `allowInsecureHttp` option.
 * @returns {string} The endpoint URL.
 */
function resolveEndpoint(baseUrl, allowInsecureHttp) {
  let url;
  try {
    url = new URL(/** @type {string} */ (baseUrl));
  } catch {
    return fail('baseUrl must be an absolute http or https URL.');
  }
  if (url.protocol !== 'http:' && url.protocol !== 'https:') {
    return fail('baseUrl must use the http or https scheme.');
  }
  if (url.protocol === 'http:' && !allowInsecureHttp && !isLoopbackHost(url.hostname)) {
    return fail(
      'baseUrl uses http://, which would send the token in clear text; ' +
      'use https:// or pass allowInsecureHttp: true.',
    );
  }
  return `${url.origin}${url.pathname.replace(/\/+$/, '')}/v1/messages`;
}

/**
 * Resolves an optional option: `undefined` takes the default, otherwise the check must pass.
 * @param {Record<string, unknown>} options - The user options.
 * @param {string} name - The option name.
 * @param {unknown} fallback - The default value.
 * @param {(value: unknown) => boolean} isValid - Validity check.
 * @param {string} rule - Human description of the rule, for the error message.
 * @returns {any} The option value.
 */
function optional(options, name, fallback, isValid, rule) {
  const value = options[name];
  if (value === undefined) {
    return fallback;
  }
  if (!isValid(value)) {
    fail(`${name} must be ${rule}.`);
  }
  return value;
}

/**
 * Validates the client options and applies the defaults.
 *
 * Every failure throws a `PidgeyError` with code `CLIENT_CONFIGURATION`, before any request.
 * Messages never include the token or any part of the URL.
 * @param {object} [options] - The options given to `new PidgeyClient()`.
 * @returns {Readonly<ClientConfig>} The frozen configuration.
 */
export function resolveConfig(options = {}) {
  const opts = /** @type {Record<string, unknown>} */ (options ?? {});
  const { token, baseUrl } = opts;
  if (typeof token !== 'string' || token.trim() === '') {
    fail('token must be a non-empty string.');
  }
  if (typeof baseUrl !== 'string') {
    fail('baseUrl must be an absolute http or https URL.');
  }
  const isBoolean = (/** @type {unknown} */ v) => typeof v === 'boolean';
  const isNonNegativeInteger = (/** @type {unknown} */ v) => Number.isInteger(v) && /** @type {number} */ (v) >= 0;
  const isPositiveInteger = (/** @type {unknown} */ v) => Number.isInteger(v) && /** @type {number} */ (v) > 0;

  const allowInsecureHttp = optional(opts, 'allowInsecureHttp', false, isBoolean, 'a boolean');
  const maxRetries = optional(opts, 'maxRetries', 2, isNonNegativeInteger, 'a non-negative integer');
  const retryDelayMs = optional(opts, 'retryDelayMs', 500, isNonNegativeInteger, 'a non-negative integer');
  const retryAmbiguous = optional(opts, 'retryAmbiguous', false, isBoolean, 'a boolean');
  const timeoutMs = optional(opts, 'timeoutMs', 30000, isPositiveInteger, 'a positive integer');
  const endpoint = resolveEndpoint(baseUrl, allowInsecureHttp);

  return Object.freeze({
    token, endpoint, allowInsecureHttp, maxRetries, retryDelayMs, retryAmbiguous, timeoutMs,
  });
}
