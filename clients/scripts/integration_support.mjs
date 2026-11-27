// Shared helper of the client integration specs (clients/nodejs and
// clients/cli import it by relative path). Everything here is read from the
// environment prepared by clients/scripts/integration.sh.
import { randomUUID } from 'node:crypto';

/**
 * Reads the integration environment.
 *
 * Returns null when PIDGEY_INTEGRATION_URL is unset and
 * PIDGEY_INTEGRATION is not "required": the spec then marks every case as
 * pending. Throws, naming the missing variables (never a value), when
 * PIDGEY_INTEGRATION is "required" and anything is missing.
 * @param {NodeJS.ProcessEnv} [env] - environment to read
 * @returns {{ url: string, mailpitUrl: string, token: string, revokedToken: string, noConnectionToken: string }|null}
 */
export function integrationEnv(env = process.env) {
  const required = env.PIDGEY_INTEGRATION === 'required';

  if (!env.PIDGEY_INTEGRATION_URL && !required) {
    return null;
  }

  const entries = [
    ['url', 'PIDGEY_INTEGRATION_URL', env.PIDGEY_INTEGRATION_URL],
    ['mailpitUrl', 'PIDGEY_INTEGRATION_MAILPIT_URL', env.PIDGEY_INTEGRATION_MAILPIT_URL],
    ['token', 'PIDGEY_INTEGRATION_TOKEN', env.PIDGEY_INTEGRATION_TOKEN],
    ['revokedToken', 'PIDGEY_INTEGRATION_REVOKED_TOKEN', env.PIDGEY_INTEGRATION_REVOKED_TOKEN],
    ['noConnectionToken', 'PIDGEY_INTEGRATION_NO_CONNECTION_TOKEN', env.PIDGEY_INTEGRATION_NO_CONNECTION_TOKEN],
  ];

  const missing = entries.filter(([, , value]) => !value).map(([, name]) => name);
  if (missing.length > 0) {
    throw new Error(`Missing integration environment variable(s): ${missing.join(', ')}`);
  }

  return Object.fromEntries(entries.map(([key, , value]) => [key, value]));
}

/**
 * Builds a subject no other run can share.
 * @param {string} prefix - readable start of the subject
 * @returns {string} "<prefix> <uuid>"
 */
export function uniqueSubject(prefix) {
  return `${prefix} ${randomUUID()}`;
}

/**
 * Lists the Mailpit messages whose subject equals the given one.
 * @param {string} mailpitUrl - Mailpit base URL (HTTP API)
 * @param {string} subject - exact subject to look for
 * @returns {Promise<Array<object>>} the matching message summaries
 */
export async function findMessages(mailpitUrl, subject) {
  const base = mailpitUrl.replace(/\/+$/, '');
  const query = encodeURIComponent(`subject:"${subject.replace(/"/g, '')}"`);
  const response = await fetch(`${base}/api/v1/search?query=${query}`);

  if (!response.ok) {
    throw new Error(`Mailpit search answered ${response.status}`);
  }

  const { messages = [] } = await response.json();
  return messages.filter((message) => message.Subject === subject);
}

/**
 * Waits for the single Mailpit message with the given subject.
 * @param {string} mailpitUrl - Mailpit base URL (HTTP API)
 * @param {string} subject - exact subject to wait for
 * @param {{ timeoutMs?: number }} [options] - polling limit
 * @returns {Promise<object>} the matching message summary
 */
export async function waitForMessage(mailpitUrl, subject, { timeoutMs = 10000 } = {}) {
  const deadline = Date.now() + timeoutMs;

  let messages = await findMessages(mailpitUrl, subject);
  while (messages.length === 0 && Date.now() < deadline) {
    await new Promise((resolve) => setTimeout(resolve, 250));
    messages = await findMessages(mailpitUrl, subject);
  }

  if (messages.length > 1) {
    throw new Error(`Expected one Mailpit message with subject "${subject}", found ${messages.length}`);
  }
  if (messages.length === 0) {
    throw new Error(`No Mailpit message with subject "${subject}" after ${timeoutMs} ms`);
  }
  return messages[0];
}
