import { resolveConfig } from './config.js';
import { PidgeyError } from './PidgeyError.js';
import {
  buildBody, failArgument, isUnambiguousNetworkError, isValidIdempotencyKey, tooLargeError,
} from './request.js';
import { mapResponse, readText } from './response.js';

/**
 * Client for the Pidgey `/v1` API.
 *
 * The configuration lives in private fields, so the token never shows up in
 * `console.log(client)`, `JSON.stringify(client)` or a logger's serializer. The client keeps
 * no per-call state: concurrent `send` calls are independent.
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
   * @throws {PidgeyError} `CLIENT_CONFIGURATION` when an option is invalid.
   */
  constructor(options = {}) {
    this.#config = resolveConfig(options);
  }

  /**
   * Sends one email with `POST /v1/messages`.
   *
   * Resolves with the API's success body exactly as returned (`{ status, smtp }`), plus
   * `replayed`: `true` when the response carries `Idempotent-Replayed: true` (a stored answer
   * was replayed and this call sent nothing). Rejects with a {@link PidgeyError} for every
   * failure, never with another error type; a mistake in the arguments rejects with
   * `CLIENT_CONFIGURATION` before any request.
   *
   * Only `to`, `subject`, `text` and `html` are sent. The values are not validated here: the
   * API is the only validator. Every call sends an `Idempotency-Key`: the caller's, or a
   * `crypto.randomUUID()` generated once per call.
   * @param {{ to?: unknown, subject?: unknown, text?: unknown, html?: unknown }} message - The message.
   * @param {object} [options] - Per-call options.
   * @param {AbortSignal} [options.signal] - Aborts the call; it then rejects with `ABORTED`.
   * @param {string} [options.idempotencyKey] - The caller's key: 1 to 255 characters in `0x21`-`0x7E`.
   * @returns {Promise<Record<string, any>>} The parsed success body, plus `replayed`.
   */
  async send(message, options) {
    const { signal, idempotencyKey } = options ?? {};
    const body = buildBody(message);
    if (idempotencyKey !== undefined && !isValidIdempotencyKey(idempotencyKey)) {
      failArgument('idempotencyKey must be 1 to 255 printable ASCII characters (0x21-0x7E).');
    }
    if (signal !== undefined && !(signal instanceof AbortSignal)) {
      failArgument('signal must be an AbortSignal.');
    }
    if (signal?.aborted) {
      throw new PidgeyError('The call was aborted before any request was made.', {
        code: 'ABORTED', possiblySent: false,
      });
    }
    return this.#attempt(body, idempotencyKey ?? crypto.randomUUID(), signal);
  }

  /**
   * Runs one attempt of a send: one request, with its own timeout.
   *
   * Resolves with the success value, or rejects with a fully classified {@link PidgeyError}
   * whose `possiblySent` says whether this attempt was ambiguous. It keeps no state, so a
   * retry loop can call it again with the same body and key.
   * @param {string} body - The JSON request body.
   * @param {string} key - The `Idempotency-Key` of the whole `send` call.
   * @param {AbortSignal | undefined} signal - The caller's signal.
   * @returns {Promise<Record<string, any>>} The parsed success body, plus `replayed`.
   */
  async #attempt(body, key, signal) {
    const { token, endpoint, timeoutMs } = this.#config;
    const timeout = new AbortController();
    const timer = setTimeout(() => timeout.abort(), timeoutMs);
    const combined = signal ? AbortSignal.any([signal, timeout.signal]) : timeout.signal;
    let response;
    let read;
    try {
      response = await fetch(endpoint, {
        method: 'POST',
        // A 3xx is returned as is (never followed, so the POST is not replayed as a GET and the
        // token goes to no other host) and mapped to INVALID_RESPONSE keeping its status.
        // `redirect: 'error'` would surface it as a network TypeError without a status.
        redirect: 'manual',
        headers: {
          Authorization: `Bearer ${token}`,
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'Idempotency-Key': key,
        },
        body,
        signal: combined,
      });
      read = await readText(response, combined);
    } catch (error) {
      throw classifyFailure(error, signal, timeout.signal);
    } finally {
      clearTimeout(timer);
    }
    if (read.tooLarge) {
      throw tooLargeError(response.status);
    }
    const parsed = mapResponse(response, read.text, token);
    return { ...parsed, replayed: response.headers.get('idempotent-replayed') === 'true' };
  }
}

/**
 * Classifies a failure of `fetch` or of the body read, once a request was dispatched.
 *
 * - The caller's signal fired: `ABORTED`, possibly sent (the status is not consulted).
 * - The per-attempt timeout fired: an ambiguous `NETWORK_ERROR`.
 * - Otherwise a `NETWORK_ERROR`, ambiguous unless the cause proves nothing was sent.
 * @param {unknown} error - What was thrown.
 * @param {AbortSignal | undefined} signal - The caller's signal.
 * @param {AbortSignal} timeoutSignal - The signal of the per-attempt timeout.
 * @returns {PidgeyError} The error to reject with.
 */
function classifyFailure(error, signal, timeoutSignal) {
  if (signal?.aborted) {
    return new PidgeyError('The call was aborted.', { code: 'ABORTED', possiblySent: true });
  }
  if (timeoutSignal.aborted) {
    return new PidgeyError('The request timed out.', { code: 'NETWORK_ERROR', possiblySent: true });
  }
  return new PidgeyError('The request failed before a response was received.', {
    code: 'NETWORK_ERROR',
    possiblySent: !isUnambiguousNetworkError(error),
  });
}
