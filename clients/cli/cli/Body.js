import { UsageError } from './Arguments.js';

/**
 * The most bytes read from stdin: 1 MiB, the request body limit of the API.
 * @type {number}
 */
export const STDIN_LIMIT = 1048576;

/**
 * The readable the body can come from.
 * @typedef {AsyncIterable<Uint8Array> & { isTTY?: boolean, destroy: () => unknown }} BodyStdin
 */

/**
 * Picks the body parts of the message. With `--text` and/or `--html` those are used and stdin
 * is not touched; with neither, stdin is the plain-text body, unless it is a TTY.
 * @param {{ text?: string, html?: string }} args - The parsed flags.
 * @param {{ stdin: BodyStdin, signal: AbortSignal }} io - Standard input and the abort signal.
 * @returns {Promise<{ text?: string, html?: string } | null>} The body parts, or `null` when the
 *   signal aborted while stdin was being read (nothing is to be sent).
 * @throws {UsageError} On a TTY stdin with no body flag, an unreadable stdin or one over 1 MiB.
 */
export async function readBody({ text, html }, { stdin, signal }) {
  if (text !== undefined || html !== undefined) {
    return {
      ...(text === undefined ? {} : { text }),
      ...(html === undefined ? {} : { html }),
    };
  }
  if (stdin.isTTY) {
    throw new UsageError('No body: pass --text or --html, or pipe the body through stdin');
  }

  const stdinText = await readStdin(stdin, signal);
  return stdinText === null ? null : { text: stdinText };
}

/**
 * Reads stdin as UTF-8 text, stopping as soon as it goes over the limit.
 * @param {BodyStdin} stdin - The readable.
 * @param {AbortSignal} signal - Aborted on SIGINT.
 * @returns {Promise<string | null>} The text, or `null` when the signal aborted.
 * @throws {UsageError} When stdin cannot be read or goes over 1 MiB.
 */
async function readStdin(stdin, signal) {
  if (signal.aborted) {
    return null;
  }

  const chunks = [];
  let total = 0;
  let overLimit = false;
  const onAbort = () => stdin.destroy();
  signal.addEventListener('abort', onAbort, { once: true });
  try {
    for await (const chunk of stdin) {
      total += chunk.length;
      if (total > STDIN_LIMIT) {
        overLimit = true;
        stdin.destroy();
        break;
      }
      chunks.push(chunk);
    }
  } catch (error) {
    if (signal.aborted) {
      return null;
    }
    const { code } = /** @type {{ code?: string }} */ (error);
    throw new UsageError(`Cannot read stdin${code ? `: ${code}` : ''}`);
  } finally {
    signal.removeEventListener('abort', onAbort);
  }

  if (overLimit) {
    throw new UsageError(`Stdin is larger than the ${STDIN_LIMIT} byte (1 MiB) limit; nothing was sent`);
  }
  return new TextDecoder('utf-8').decode(concat(chunks, total));
}

/**
 * Joins chunks into one buffer.
 * @param {Uint8Array[]} chunks - The chunks.
 * @param {number} total - Their total length.
 * @returns {Uint8Array} The joined bytes.
 */
function concat(chunks, total) {
  const joined = new Uint8Array(total);
  let offset = 0;
  for (const chunk of chunks) {
    joined.set(chunk, offset);
    offset += chunk.length;
  }
  return joined;
}
