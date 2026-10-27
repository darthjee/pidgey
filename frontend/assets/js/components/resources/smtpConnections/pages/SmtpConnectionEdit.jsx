import { useMemo } from 'react';
import Router from '../../../../utils/routing/Router.js';
import SmtpConnectionForm from './SmtpConnectionForm.jsx';

/**
 * Read the current browser location hash, SSR/spec-safe.
 *
 * @returns {string} The current `window.location.hash`, or `''` when `window` is not defined
 *   (e.g. during a Node-based spec run).
 */
function currentHash() {
  return typeof window === 'undefined' ? '' : window.location.hash;
}

/**
 * SMTP connection edit page (`#/smtp-connections/:id/edit`): the shared form, for the id
 * taken from the route.
 *
 * @returns {React.ReactElement} The rendered edit page.
 */
export default function SmtpConnectionEdit() {
  const id = useMemo(
    () => Router.extractParams('/smtp-connections/:id/edit', currentHash())?.id,
    [],
  );

  return <SmtpConnectionForm id={id} />;
}
