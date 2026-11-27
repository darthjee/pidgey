import { constants } from 'node:fs';
import { open } from 'node:fs/promises';
import { UsageError } from './Arguments.js';

const TOKEN_FILE_LIMIT = 4096;
// eslint-disable-next-line no-control-regex -- matching control characters is the point
const CONTROL_CHARACTER =/[\u0000-\u001f\u007f]/;

/**
 * Resolves the API token and the base URL from the parsed flags and the environment.
 * The token comes from `--token-file`, otherwise from `PIDGEY_TOKEN`; the URL from `--url`,
 * otherwise from `PIDGEY_URL`. The token is never taken from the command line.
 * @param {{ tokenFile?: string, url?: string }} args - The parsed flags.
 * @param {{ env: Record<string, string | undefined>, stderr: { write: (text: string) => unknown },
 *   platform: string }} io - The environment, the error stream (for the mode warning) and the platform.
 * @returns {Promise<{ token: string, baseUrl: string }>} The token and the base URL.
 * @throws {UsageError} When either is missing or the token file is not acceptable.
 */
export async function resolveCredentials(args, { env, stderr, platform }) {
  const token = args.tokenFile === undefined
    ? tokenFromEnvironment(env)
    : await readTokenFile(args.tokenFile, { stderr, platform });

  const baseUrl = args.url || env.PIDGEY_URL;
  if (!baseUrl) {
    throw new UsageError('Missing URL: pass --url or set PIDGEY_URL');
  }

  return { token, baseUrl };
}

/**
 * Takes the token from `PIDGEY_TOKEN`.
 * @param {Record<string, string | undefined>} env - The environment variables.
 * @returns {string} The token.
 * @throws {UsageError} When `PIDGEY_TOKEN` is unset or empty.
 */
function tokenFromEnvironment(env) {
  if (!env.PIDGEY_TOKEN) {
    throw new UsageError('Missing token: pass --token-file or set PIDGEY_TOKEN');
  }
  return env.PIDGEY_TOKEN;
}

/**
 * Reads the token from a file, through a single descriptor, following the rules of the spec.
 * @param {string} path - The token file path.
 * @param {{ stderr: { write: (text: string) => unknown }, platform: string }} io - The error
 *   stream and the platform.
 * @returns {Promise<string>} The token.
 * @throws {UsageError} On any problem with the file; the message names the path, never the content.
 */
async function readTokenFile(path, { stderr, platform }) {
  let handle;
  try {
    // O_NONBLOCK keeps the open of a FIFO from blocking; the fstat below refuses it anyway.
    // The constant is undefined on Windows, where `|` reads it as 0.
    handle = await open(path, constants.O_RDONLY | constants.O_NONBLOCK);
  } catch (error) {
    throw unreadable(path, error);
  }

  let stat;
  let bytes;
  try {
    stat = await handle.stat();
    // A FIFO, a device or a directory is refused before anything is read from it.
    if (stat.isFile()) {
      bytes = await readAtMost(handle, TOKEN_FILE_LIMIT + 1);
    }
  /* c8 ignore start */
  // A failing fstat or read on a descriptor that just opened cannot be provoked from a spec.
  } catch (error) {
    throw unreadable(path, error);
  /* c8 ignore stop */
  } finally {
    await handle.close();
  }

  if (bytes === undefined) {
    throw new UsageError(`Token file '${path}' is not a regular file`);
  }
  if (platform !== 'win32' && (stat.mode & 0o077) !== 0) {
    stderr.write(`pidgey: warning: token file '${path}' is readable by group or others\n`);
  }
  if (bytes.length > TOKEN_FILE_LIMIT) {
    throw new UsageError(`Token file '${path}' is larger than the ${TOKEN_FILE_LIMIT} byte (4 KiB) limit`);
  }
  return parseToken(new TextDecoder().decode(bytes), path);
}

/**
 * Reads up to `limit` bytes from a file handle, from its start.
 * @param {{ read: (buffer: Uint8Array, offset: number, length: number, position: number | null)
 *   => Promise<{ bytesRead: number }> }} handle - The open file.
 * @param {number} limit - The most bytes to read.
 * @returns {Promise<Uint8Array>} The bytes read.
 */
async function readAtMost(handle, limit) {
  const buffer = new Uint8Array(limit);
  let total = 0;
  while (total < limit) {
    const { bytesRead } = await handle.read(buffer, total, limit - total, total);
    if (bytesRead === 0) {
      break;
    }
    total += bytesRead;
  }
  return buffer.subarray(0, total);
}

/**
 * Trims one trailing newline and validates the token text.
 * @param {string} text - The file content.
 * @param {string} path - The token file path, for the messages.
 * @returns {string} The token.
 * @throws {UsageError} When the token is empty or holds a control character.
 */
function parseToken(text, path) {
  const token = text.replace(/\r?\n$/, '');
  if (token.trim() === '') {
    throw new UsageError(`Token file '${path}' is empty`);
  }
  if (CONTROL_CHARACTER.test(token)) {
    throw new UsageError(`Token file '${path}' holds a control character`);
  }
  return token;
}

/**
 * Builds the error for a token file that could not be opened or read.
 * @param {string} path - The token file path.
 * @param {unknown} error - The system error.
 * @returns {UsageError} The usage error, naming the path and the error code only.
 */
function unreadable(path, error) {
  const { code } = /** @type {{ code: string }} */ (error);
  return new UsageError(`Cannot read token file '${path}': ${code}`);
}
