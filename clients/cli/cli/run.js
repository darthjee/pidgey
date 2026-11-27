import { PidgeyClient, VERSION as LIBRARY_VERSION } from 'pidgey-client';
import { parseArguments, USAGE } from './Arguments.js';
import { readBody } from './Body.js';
import { resolveCredentials } from './Credentials.js';
import { EXIT, exitCodeFor } from './ExitCode.js';
import { formatFailure, formatResult } from './Output.js';
import { VERSION } from './version.js';

/**
 * The part of the library client the CLI uses.
 * @typedef {object} SendClient
 * @property {(message: object, options: object) => Promise<object>} send - Sends the message.
 */

/**
 * The environment the CLI runs in, injected so specs can drive it in-process.
 * @typedef {object} RunIo
 * @property {Record<string, string | undefined>} env - The environment variables.
 * @property {import('./Body.js').BodyStdin} stdin - The standard input.
 * @property {{ write: (text: string) => unknown }} stdout - The standard output.
 * @property {{ write: (text: string) => unknown }} stderr - The standard error.
 * @property {AbortSignal} signal - Aborted on SIGINT.
 * @property {(options: object) => SendClient} [createClient] - Builds the library client;
 *   defaults to `new PidgeyClient(options)`.
 * @property {string} [platform] - The platform; defaults to `process.platform`.
 */

/**
 * Runs the `pidgey` command.
 * @param {string[]} argv - The arguments (without the node binary and the script path).
 * @param {RunIo} io - The environment: variables, streams, abort signal and factories.
 * @returns {Promise<number>} The exit code: `0` sent, `1` a failed send, `2` a usage error and
 *   `130` interrupted.
 */
export async function run(argv, io) {
  try {
    const args = parseArguments(argv);
    if (args.mode === 'help') {
      io.stdout.write(USAGE);
      return EXIT.OK;
    }
    if (args.mode === 'version') {
      io.stdout.write(`pidgey-cli ${VERSION} (pidgey-client ${LIBRARY_VERSION})\n`);
      return EXIT.OK;
    }

    const result = await send(args, io);
    if (result === null) {
      return EXIT.INTERRUPTED;
    }
    if (args.verbose) {
      io.stdout.write(formatResult(result));
    }
    return EXIT.OK;
  } catch (error) {
    io.stderr.write(formatFailure(error));
    return exitCodeFor(error, io.signal);
  }
}

/**
 * Sends the message: credentials, body, client, `send`.
 * @param {import('./Arguments.js').SendArguments} args - The parsed flags.
 * @param {RunIo} io - The environment.
 * @returns {Promise<object | null>} What `send` resolved with, or `null` when interrupted while
 *   stdin was being read.
 * @throws {Error} A `UsageError`, or a `PidgeyError` from the library.
 */
async function send(args, io) {
  const { token, baseUrl } = await resolveCredentials(args, {
    env: io.env,
    stderr: io.stderr,
    platform: io.platform ?? process.platform,
  });
  const body = await readBody(args, io);
  if (body === null) {
    return null;
  }

  const createClient = io.createClient ?? ((options) => new PidgeyClient(options));
  const client = createClient({
    token,
    baseUrl,
    retryAmbiguous: args.retryAmbiguous,
    allowInsecureHttp: args.allowInsecureHttp,
  });
  return client.send(
    { to: args.to, subject: args.subject, ...body },
    {
      signal: io.signal,
      ...(args.idempotencyKey === undefined ? {} : { idempotencyKey: args.idempotencyKey }),
    },
  );
}
