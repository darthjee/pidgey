import ApiError from './ApiError.js';
import AuthSession from './AuthSession.js';
import LoginModalEvents from './LoginModalEvents.js';
import parseBody from './parseBody.js';

const REFRESH_PATH = '/auth/refresh.json';
const REFRESH_LOCK_NAME = 'pidgey-refresh';
const FALLBACK_ERROR_MESSAGE = 'Request failed';

/**
 * The refresh currently in flight in this tab, shared by every request that got a `401`
 * while it runs; `null` when no refresh is running.
 *
 * @type {Promise<boolean>|null}
 */
let refreshInFlight = null;

/**
 * Build the {@link ApiError} for a failed response.
 *
 * @description Reads the backend's standard error body
 * (`{ error: { code, message, details? }, statusCode, timestamp }`). Falls back to the
 * response's `statusText`, then to a generic message, when the body carries no
 * `error.message` (e.g. a proxy-generated or empty error response), so the resulting error's
 * `message` is never `undefined`.
 * @param {Response} response - The raw `fetch` response.
 * @param {object} data - The parsed response body.
 * @returns {ApiError} The error to throw.
 */
function buildApiError(response, data) {
  const error = data?.error ?? {};
  const message = error.message || response.statusText || FALLBACK_ERROR_MESSAGE;

  return new ApiError(response.status, message, error.code, error.details);
}

/**
 * Perform a raw JSON request, without any `401`-recovery behavior.
 *
 * @param {string} method - HTTP method.
 * @param {string} path - Request path.
 * @param {object} [body] - Fields to serialize as the JSON request body; when omitted, no
 *   body and no `Content-Type` header are sent.
 * @returns {Promise<{response: Response, data: object}>} The raw response and its parsed
 *   JSON body.
 */
async function request(method, path, body) {
  const bodyOptions = body === undefined
    ? { headers: { Accept: 'application/json' } }
    : { headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify(body) };
  const response = await fetch(path, { method, credentials: 'same-origin', ...bodyOptions });

  const data = await parseBody(response);

  return { response, data };
}

/**
 * End the client-side session: clear the stored refresh token and open the login modal in
 * Password mode via the shared {@link LoginModalEvents} bus — a pure client-side state
 * transition, with no API call of its own. SSR/spec-safe — a no-op when `window` is not
 * defined, the same way the shared `redirectHome` helper guards it.
 *
 * @returns {undefined} Always `undefined`, so callers can `return` it directly.
 */
function sessionExpired() {
  AuthSession.clear();

  if (typeof window !== 'undefined') {
    LoginModalEvents.open('password');
  }

  return undefined;
}

/**
 * Submit a JSON request, always sending same-origin credentials so the session cookie set
 * by the backend (via `Set-Cookie`) is stored/sent automatically. On a `401`, delegates to
 * {@link handleUnauthorized} unless this is already a retried request, in which case the
 * session is treated as expired directly.
 *
 * @param {string} method - HTTP method.
 * @param {string} path - Request path.
 * @param {object} [body] - Fields to serialize as the JSON request body; omitted for a GET.
 * @param {boolean} [isRetry] - Whether this call is already a post-refresh retry.
 * @returns {Promise<object>} The parsed JSON response body, on success; `undefined` when the
 *   session turned out to be expired.
 */
async function sendJson(method, path, body, isRetry = false) {
  const { response, data } = await request(method, path, body);

  if (response.ok) {
    return data;
  }

  if (response.status === 401) {
    return isRetry
      ? sessionExpired()
      : handleUnauthorized(method, path, body);
  }

  throw buildApiError(response, data);
}

/**
 * Recover from a `401` on the original request: join the tab's single-flight refresh (see
 * {@link sharedRefresh}) and retry the original request once when it succeeds. Every request
 * that got a `401` while a refresh is in flight waits for that same refresh instead of
 * starting its own, so the stored refresh token is never presented twice. When the shared
 * refresh fails, the session has already been ended once by the refresh itself, so this
 * resolves to `undefined` without ending it again.
 *
 * @param {string} method - Original request's HTTP method.
 * @param {string} path - Original request's path.
 * @param {object} [body] - Original request's JSON body; `undefined` for a GET.
 * @returns {Promise<object>} The retried request's parsed JSON body, on success; `undefined`
 *   when the session turned out to be expired.
 */
async function handleUnauthorized(method, path, body) {
  const staleToken = AuthSession.get();
  const refreshed = await sharedRefresh(staleToken);

  return refreshed ? sendJson(method, path, body, true) : undefined;
}

/**
 * Join the refresh in flight in this tab, or start one.
 *
 * @description Single flight: while a refresh runs, every caller gets the same promise, so
 * only one `POST /auth/refresh.json` is made. The promise is cleared once it settles, so a
 * later `401` starts a new refresh. The single flight stays outside the cross-tab lock (see
 * {@link refreshSession}), so a tab holds at most one lock request at a time.
 * @param {string|null} staleToken - The stored refresh token read when the `401` happened.
 * @returns {Promise<boolean>} `true` when the original requests should be retried, `false`
 *   when the session was ended.
 */
function sharedRefresh(staleToken) {
  if (!refreshInFlight) {
    refreshInFlight = refreshSession(staleToken).finally(() => {
      refreshInFlight = null;
    });
  }

  return refreshInFlight;
}

/**
 * Refresh the session under the cross-tab refresh lock.
 *
 * @description Runs under {@link withRefreshLock}, so only one tab rotates the token at a
 * time. Once the lock is held, the stored refresh token is read again:
 * - missing (another tab's refresh failed, or the user logged out) → the session is ended;
 * - different from `staleToken` (another tab already rotated it, and the shared access cookie
 *   is fresh) → no refresh call is made and the requests are retried;
 * - unchanged → the token is rotated through {@link rotateToken}.
 * @param {string|null} staleToken - The stored refresh token read when the `401` happened.
 * @returns {Promise<boolean>} `true` when the original requests should be retried, `false`
 *   when the session was ended.
 */
function refreshSession(staleToken) {
  return withRefreshLock(async () => {
    const currentToken = AuthSession.get();

    if (!currentToken) {
      return expireSession();
    }

    if (currentToken !== staleToken) {
      return true;
    }

    return rotateToken(currentToken);
  });
}

/**
 * Run `callback` while holding the cross-tab `'pidgey-refresh'` Web Locks API lock.
 *
 * @description Falls back to running `callback` directly when `navigator.locks` is not
 * available (old browsers, Node-based specs), in which case only the in-tab single flight
 * applies.
 * @param {Function} callback - Async function to run under the lock.
 * @returns {Promise<*>} The callback's result.
 */
function withRefreshLock(callback) {
  if (typeof navigator !== 'undefined' && navigator.locks) {
    return navigator.locks.request(REFRESH_LOCK_NAME, callback);
  }

  return callback();
}

/**
 * Rotate the refresh token and store the new one.
 *
 * @description Ends the session (once, via {@link sessionExpired}) when the API rejects the
 * refresh, or when the refresh request itself fails (e.g. a network error) — a rejected
 * `fetch` is treated as a failed refresh rather than rethrown, so every waiting request
 * resolves the same way.
 * @param {string} refreshToken - The refresh token to present.
 * @returns {Promise<boolean>} `true` when the token was rotated and the requests should be
 *   retried, `false` when the session was ended.
 */
async function rotateToken(refreshToken) {
  try {
    const { response, data } = await request('POST', REFRESH_PATH, { refreshToken });

    if (!response.ok) {
      return expireSession();
    }

    AuthSession.set(data.refreshToken);
    return true;
  } catch {
    return expireSession();
  }
}

/**
 * End the session on behalf of the shared refresh.
 *
 * @returns {boolean} Always `false`, the "session ended" refresh outcome.
 */
function expireSession() {
  sessionExpired();
  return false;
}

/**
 * Generic JSON HTTP client used by resource-specific clients. Transparently recovers from an
 * expired access token: on a `401`, it joins a single-flight refresh (one
 * `POST /auth/refresh.json` shared by every request of the tab that got a `401`) and retries
 * the original request once; if there is no refresh token to use, or the shared refresh
 * fails, the session is ended once and every waiting request resolves to `undefined`. When
 * `navigator.locks` is available, the refresh also runs under a cross-tab
 * `'pidgey-refresh'` lock and skips the refresh call when another tab already rotated the
 * stored token. Methods: `getJson` (no body), `postJson`, `patchJson` and `deleteJson`.
 */
const ApiClient = {
  /**
   * Submit a GET request, with no request body and no `Content-Type` header. Same
   * credentials/error-handling/`401`-recovery shape as {@link ApiClient.postJson}.
   *
   * @param {string} path - Request path.
   * @returns {Promise<object>} The parsed JSON response body, on success; `undefined` when the
   *   session turned out to be expired.
   */
  async getJson(path) {
    return sendJson('GET', path);
  },

  /**
   * Submit a POST request with a JSON body. Always sends same-origin credentials so the
   * session cookie set by the backend (via `Set-Cookie`) is stored/sent automatically.
   *
   * @param {string} path - Request path.
   * @param {object} body - Fields to serialize as the JSON request body.
   * @returns {Promise<object>} The parsed JSON response body, on success; `undefined` when the
   *   session turned out to be expired.
   */
  async postJson(path, body) {
    return sendJson('POST', path, body);
  },

  /**
   * Submit a DELETE request with a JSON body. Same headers/credentials/error-handling shape
   * as {@link ApiClient.postJson}.
   *
   * @param {string} path - Request path.
   * @param {object} body - Fields to serialize as the JSON request body.
   * @returns {Promise<object>} The parsed JSON response body, on success; `undefined` when the
   *   session turned out to be expired.
   */
  async deleteJson(path, body) {
    return sendJson('DELETE', path, body);
  },

  /**
   * Submit a PATCH request with a JSON body. Same headers/credentials/error-handling shape
   * as {@link ApiClient.postJson}.
   *
   * @param {string} path - Request path.
   * @param {object} body - Fields to serialize as the JSON request body.
   * @returns {Promise<object>} The parsed JSON response body, on success; `undefined` when the
   *   session turned out to be expired.
   */
  async patchJson(path, body) {
    return sendJson('PATCH', path, body);
  },
};

export default ApiClient;
