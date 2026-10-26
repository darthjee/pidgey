/**
 * Sends the browser to the home route.
 *
 * @description Sets `window.location.hash` to `'/'`. A no-op when `window` is undefined
 * (server-side rendering or Node-based specs). The global `window` is read at call time.
 * @returns {void}
 */
export const redirectHome = () => {
  if (typeof window === 'undefined') {
    return;
  }
  window.location.hash = '/';
};

/**
 * Sends the browser to a hash route.
 *
 * @description Sets `window.location.hash` to `path`. A no-op when `window` is undefined
 * (server-side rendering or Node-based specs). The global `window` is read at call time.
 * @param {string} path - The route path (e.g. `'/smtp-connections'`).
 * @returns {void}
 */
export const redirectTo = (path) => {
  if (typeof window === 'undefined') {
    return;
  }
  window.location.hash = path;
};

/**
 * Redirects home when the given error is a 403 Forbidden response.
 *
 * @description Checks the error status and, for 403, delegates to `redirectHome()`.
 * @param {{ status?: number }} error - The error thrown by an API call.
 * @returns {boolean} `true` when the error was a 403 and the redirect was performed,
 * `false` otherwise.
 */
export const redirectIfForbidden = (error) => {
  if (error.status !== 403) {
    return false;
  }
  redirectHome();
  return true;
};
