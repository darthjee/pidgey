// Seeds the fixtures the client integration specs need, through the admin
// JSON API of a running Pidgey (see clients/scripts/integration.sh).
//
// Usage: node integration_seed.mjs > private-file
// Reads the Pidgey base URL from PIDGEY_INTEGRATION_URL.
//
// Writes PIDGEY_INTEGRATION_TOKEN, PIDGEY_INTEGRATION_REVOKED_TOKEN and
// PIDGEY_INTEGRATION_NO_CONNECTION_TOKEN as KEY=value lines to the standard
// output, which the caller redirects to a private (mode 0600) file, never to
// the job output. A failure reports the method, path, status and error code
// on the standard error, never a token or a cookie.
import { randomBytes } from 'node:crypto';

const PASSWORD = 'integration-secret-1';

/**
 * Stops the seed with a message that carries no secret.
 * @param {string} message - what went wrong
 * @returns {never}
 */
function fail(message) {
  console.error(`integration seed: ${message}`);
  process.exit(1);
}

/**
 * Extracts the access_token cookie pair from a response.
 * @param {Response} response - a register response
 * @returns {string|null} "access_token=<jwt>", or null when none was set
 */
function accessTokenCookie(response) {
  const cookies = typeof response.headers.getSetCookie === 'function'
    ? response.headers.getSetCookie()
    : [response.headers.get('set-cookie') || ''];

  for (const cookie of cookies) {
    const match = /(?:^|,\s*)(access_token=[^;,\s]+)/.exec(cookie);
    if (match) {
      return match[1];
    }
  }
  return null;
}

/**
 * Calls the Pidgey JSON API and exits on any non-2xx answer.
 * @param {string} baseUrl - Pidgey base URL
 * @param {string} method - HTTP method
 * @param {string} path - request path
 * @param {object} [options] - request options
 * @param {object} [options.body] - JSON body
 * @param {string} [options.cookie] - Cookie header value
 * @returns {Promise<{ body: any, response: Response }>} the parsed answer
 */
async function call(baseUrl, method, path, { body, cookie } = {}) {
  const headers = { Accept: 'application/json' };
  if (body !== undefined) {
    headers['Content-Type'] = 'application/json';
  }
  if (cookie) {
    headers.Cookie = cookie;
  }

  let response;
  try {
    response = await fetch(`${baseUrl}${path}`, {
      method,
      headers,
      body: body === undefined ? undefined : JSON.stringify(body),
    });
  } catch (error) {
    return fail(`${method} ${path} could not be sent (${error.cause?.code || error.message})`);
  }

  const text = await response.text();
  let parsed = null;
  try {
    parsed = text ? JSON.parse(text) : null;
  } catch {
    parsed = null;
  }

  if (!response.ok) {
    const code = parsed?.error?.code || 'no error code';
    return fail(`${method} ${path} answered ${response.status} ${code}`);
  }
  return { body: parsed, response };
}

/**
 * Registers an organization with its first user.
 * @param {string} baseUrl - Pidgey base URL
 * @param {string} label - organization label ("a" or "b")
 * @returns {Promise<string>} the session cookie header value
 */
async function register(baseUrl, label) {
  const suffix = randomBytes(6).toString('hex');
  const { response } = await call(baseUrl, 'POST', '/auth/register.json', {
    body: {
      username: `integration-${label}-${suffix}`,
      email: `integration-${label}-${suffix}@example.com`,
      password: PASSWORD,
      organizationName: `Integration ${label} ${suffix}`,
    },
  });

  const cookie = accessTokenCookie(response);
  if (!cookie) {
    return fail('POST /auth/register.json set no access_token cookie');
  }
  return cookie;
}

/**
 * Creates a send token.
 * @param {string} baseUrl - Pidgey base URL
 * @param {string} cookie - session cookie
 * @param {string} name - token name
 * @returns {Promise<{ id: number, token: string }>} the id and plain token
 */
async function createToken(baseUrl, cookie, name) {
  const { body } = await call(baseUrl, 'POST', '/api-tokens.json', {
    cookie,
    body: { name, scopes: ['send'] },
  });
  return { id: body.apiToken.id, token: body.apiToken.token };
}

/**
 * Runs the seed.
 * @returns {Promise<void>}
 */
async function main() {
  const baseUrl = (process.env.PIDGEY_INTEGRATION_URL || '').replace(/\/+$/, '');

  if (!baseUrl) {
    fail('PIDGEY_INTEGRATION_URL is not set');
  }

  const cookieA = await register(baseUrl, 'a');
  const { body: connection } = await call(baseUrl, 'POST', '/smtp-connections.json', {
    cookie: cookieA,
    body: {
      name: 'Mailpit',
      host: 'mailpit-plain',
      port: 2025,
      encryption: 'none',
      fromAddress: 'noreply@example.com',
    },
  });
  await call(baseUrl, 'POST', `/smtp-connections/${connection.smtpConnection.id}/activate.json`, {
    cookie: cookieA,
  });

  const active = await createToken(baseUrl, cookieA, 'integration active');
  const revoked = await createToken(baseUrl, cookieA, 'integration revoked');
  await call(baseUrl, 'DELETE', `/api-tokens/${revoked.id}.json`, { cookie: cookieA });

  const cookieB = await register(baseUrl, 'b');
  const noConnection = await createToken(baseUrl, cookieB, 'integration no connection');

  const lines = [
    `PIDGEY_INTEGRATION_TOKEN=${active.token}`,
    `PIDGEY_INTEGRATION_REVOKED_TOKEN=${revoked.token}`,
    `PIDGEY_INTEGRATION_NO_CONNECTION_TOKEN=${noConnection.token}`,
  ];
  process.stdout.write(`${lines.join('\n')}\n`);
}

await main();
