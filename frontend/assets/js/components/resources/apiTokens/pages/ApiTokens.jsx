import { useEffect, useMemo, useState } from 'react';
import ApiTokensController from './controllers/ApiTokensController.js';
import ApiTokensHelper from './helpers/ApiTokensHelper.jsx';

const INITIAL_TOKENS = [];
const INITIAL_LOAD_STATE = { loading: true, loadError: null };

/**
 * Build the mount-time load effect: triggers `controller.load()` once, unconditionally — a
 * logged-out `401` is handled entirely by `ApiClient`'s refresh/login-modal flow. Extracted as
 * a plain function so it can be exercised directly in tests without a React renderer.
 *
 * @param {ApiTokensController} controller - The page's controller.
 * @returns {Function} Effect callback.
 */
export function buildLoadEffect(controller) {
  return () => {
    controller.load();
  };
}

/**
 * API tokens page: lists the organization's API tokens (revoked ones included), with a
 * per-row Revoke action on active tokens. Open to any logged-in, approved user — there is no
 * client-side login or admin check; a `401` is handled by `ApiClient`.
 *
 * @returns {React.ReactElement} The rendered API tokens page.
 */
export default function ApiTokens() {
  const [tokens, setTokens] = useState(INITIAL_TOKENS);
  const [loadState, setLoadState] = useState(INITIAL_LOAD_STATE);
  const [listError, setListError] = useState(null);

  const controller = useMemo(
    () => new ApiTokensController({ setTokens, setLoadState, setListError }),
    [],
  );

  useEffect(() => buildLoadEffect(controller)(), [controller]);

  return ApiTokensHelper.render(
    { tokens, ...loadState, listError },
    { onRevoke: (token) => () => controller.handleRevoke(token) },
  );
}
