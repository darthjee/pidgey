/**
 * The retry policy of `PidgeyClient#send`: which failures are retried, how long to wait
 * between attempts and an abortable wait. Internal: not exported from the package.
 */

/** Longest wait between two attempts, in milliseconds. */
export const MAX_BACKOFF_MS = 8000;

/** Codes that are never retried, whatever the options. */
const NEVER_RETRIED = new Set([
  'INVALID_RESPONSE',
  'ABORTED',
  'CLIENT_CONFIGURATION',
  'SMTP_PROTOCOL_ERROR',
  'IDEMPOTENCY_KEY_IN_USE',
  'IDEMPOTENCY_KEY_REUSED',
]);

/** Codes retried whenever the attempt was not ambiguous. */
const TRANSIENT = new Set(['NETWORK_ERROR', 'SMTP_CONNECTION_FAILED', 'SMTP_TEMPORARY_FAILURE']);

/** `SMTP_*` codes the policy knows by name; they are never treated as unknown. */
const KNOWN_SMTP = new Set([
  'SMTP_CONNECTION_FAILED',
  'SMTP_TEMPORARY_FAILURE',
  'SMTP_TIMEOUT',
  'SMTP_REJECTED',
  'SMTP_AUTH_FAILED',
  'SMTP_TLS_FAILED',
  'SMTP_PROTOCOL_ERROR',
]);

/**
 * Computes the wait before a retry: exponential from `retryDelayMs`, capped at
 * {@link MAX_BACKOFF_MS}. The cap also applies to the first wait, so a base above 8000 ms
 * waits 8000 ms.
 * @param {number} retryDelayMs - The base delay in milliseconds.
 * @param {number} retry - The 1-based number of the retry about to run.
 * @returns {number} The delay in milliseconds.
 */
export function backoffDelay(retryDelayMs, retry) {
  return Math.min(retryDelayMs * 2 ** (retry - 1), MAX_BACKOFF_MS);
}

/**
 * Decides whether a failed attempt is retried. It looks at `code`, `statusCode` and
 * `possiblySent` only, never at `smtp`.
 *
 * In order: the never-retried codes; an ambiguous attempt, only with `retryAmbiguous`; the
 * transient codes; `SMTP_TIMEOUT`; an unknown `SMTP_*` code on `503` or `504`; anything else
 * is not retried.
 * @param {{ code?: string, statusCode?: number, possiblySent?: boolean }} error - The failure.
 * @param {boolean} retryAmbiguous - Whether ambiguous failures may be retried.
 * @returns {boolean} `true` when the call should be attempted again.
 */
export function shouldRetry(error, retryAmbiguous) {
  const { code = '', statusCode, possiblySent } = error;
  if (NEVER_RETRIED.has(code)) {
    return false;
  }
  if (possiblySent === true && !retryAmbiguous) {
    return false;
  }
  if (TRANSIENT.has(code) || code === 'SMTP_TIMEOUT') {
    return true;
  }
  return code.startsWith('SMTP_') && !KNOWN_SMTP.has(code)
    && (statusCode === 503 || statusCode === 504);
}

/**
 * Waits `ms` milliseconds, or rejects as soon as `signal` fires. The timer is always cleared,
 * so no handle stays open. What it rejects with is internal: the caller turns any rejection
 * into `ABORTED`.
 * @param {number} ms - The time to wait in milliseconds.
 * @param {AbortSignal} [signal] - Cancels the wait.
 * @returns {Promise<void>} Resolves when the time has passed.
 */
export function wait(ms, signal) {
  return new Promise((resolve, reject) => {
    if (signal?.aborted) {
      reject(signal.reason);
      return;
    }
    const onAbort = () => {
      clearTimeout(timer);
      reject(signal?.reason);
    };
    const timer = setTimeout(() => {
      signal?.removeEventListener('abort', onAbort);
      resolve();
    }, ms);
    signal?.addEventListener('abort', onAbort, { once: true });
  });
}
