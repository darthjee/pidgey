import { parseArgs } from 'node:util';

/**
 * The usage text of the `pidgey` command.
 * @type {string}
 */
export const USAGE = `Usage: pidgey --to <address> --subject <text> [--text <body>] [--html <body>] [options]
       pidgey --help | --version

Sends one message through a Pidgey server. Without --text and --html, the body is read
from stdin (at most 1 MiB; a TTY stdin is an error).

Options:
  --to <address>            Recipient address (required).
  --subject <text>          Subject (required).
  --text <body>             Plain-text body.
  --html <body>             HTML body.
  --token-file <path>       File holding the API token (at most 4 KiB).
                            Without it, the token comes from PIDGEY_TOKEN.
  --url <url>               Pidgey base URL. Without it, the URL comes from PIDGEY_URL.
  --idempotency-key <key>   Idempotency key (1 to 255 printable ASCII characters).
  --retry-ambiguous         Retry also when the server may have sent the message.
  --allow-insecure-http     Allow an http:// URL on a non-loopback host.
  --verbose                 Print the result on success.
  --help                    Print this usage and exit.
  --version                 Print the CLI and library versions and exit.

The token is never taken from the command line.

Exit codes:
  0    The message was sent.
  1    The send failed.
  2    Usage error.
  130  Interrupted by SIGINT.
`;

/**
 * Raised when the command line or the environment is not valid. The CLI answers it with exit 2.
 */
export class UsageError extends Error {
  /**
   * Builds a UsageError.
   * @param {string} message - One-line description of the mistake.
   * @param {{ showUsage?: boolean }} [options] - `showUsage` asks the CLI to print the usage
   *   after the message; only parse errors set it.
   */
  constructor(message, { showUsage = false } = {}) {
    super(message);
    this.name = 'UsageError';
    /** @type {boolean} */
    this.showUsage = showUsage;
  }
}

const OPTIONS = /** @type {const} */ ({
  to: { type: 'string' },
  subject: { type: 'string' },
  text: { type: 'string' },
  html: { type: 'string' },
  'token-file': { type: 'string' },
  url: { type: 'string' },
  'idempotency-key': { type: 'string' },
  'retry-ambiguous': { type: 'boolean' },
  'allow-insecure-http': { type: 'boolean' },
  verbose: { type: 'boolean' },
  help: { type: 'boolean' },
  version: { type: 'boolean' },
});

/**
 * What a send needs from the command line.
 * @typedef {object} SendArguments
 * @property {'send'} mode - The send mode.
 * @property {string} to - The recipient.
 * @property {string} subject - The subject.
 * @property {string | undefined} text - The plain-text body, when given (possibly empty).
 * @property {string | undefined} html - The HTML body, when given (possibly empty).
 * @property {string | undefined} tokenFile - The token file path, when given.
 * @property {string | undefined} url - The base URL, when given.
 * @property {string | undefined} idempotencyKey - The idempotency key, when given.
 * @property {boolean} retryAmbiguous - Whether to retry ambiguous failures.
 * @property {boolean} allowInsecureHttp - Whether to allow cleartext HTTP.
 * @property {boolean} verbose - Whether to print the result on success.
 */

/**
 * Parses the command line arguments (without the node binary and the script path).
 * @param {string[]} argv - The arguments.
 * @returns {{ mode: 'help' } | { mode: 'version' } | SendArguments} What was asked for.
 * @throws {UsageError} On an unknown flag, a positional argument, a repeated flag, a missing
 *   value or a missing required flag. The error asks for the usage to be printed.
 */
export function parseArguments(argv) {
  const { values, tokens } = parse(argv);

  rejectRepeated(tokens);

  if (values.help) {
    return { mode: 'help' };
  }
  if (values.version) {
    return { mode: 'version' };
  }

  for (const required of ['to', 'subject']) {
    if (values[required] === undefined) {
      throw new UsageError(`Missing required option '--${required}'`, { showUsage: true });
    }
  }

  return {
    mode: 'send',
    to: /** @type {string} */ (values.to),
    subject: /** @type {string} */ (values.subject),
    text: /** @type {string | undefined} */ (values.text),
    html: /** @type {string | undefined} */ (values.html),
    tokenFile: /** @type {string | undefined} */ (values['token-file']),
    url: /** @type {string | undefined} */ (values.url),
    idempotencyKey: /** @type {string | undefined} */ (values['idempotency-key']),
    retryAmbiguous: values['retry-ambiguous'] === true,
    allowInsecureHttp: values['allow-insecure-http'] === true,
    verbose: values.verbose === true,
  };
}

/**
 * Runs `parseArgs` in strict mode and turns its errors into UsageErrors.
 * @param {string[]} argv - The arguments.
 * @returns {{ values: Record<string, unknown>, tokens: Array<{ kind: string, name: string }> }}
 *   The parsed values and tokens.
 */
function parse(argv) {
  try {
    const { values, tokens } = parseArgs({
      args: argv,
      options: OPTIONS,
      strict: true,
      allowPositionals: false,
      tokens: true,
    });
    return { values, tokens };
  } catch (error) {
    throw new UsageError(/** @type {Error} */ (error).message.split('\n')[0], { showUsage: true });
  }
}

/**
 * Rejects a flag given more than once.
 * @param {Array<{ kind: string, name: string }>} tokens - The parsed tokens.
 * @returns {void}
 * @throws {UsageError} When a flag appears twice.
 */
function rejectRepeated(tokens) {
  const seen = new Set();
  for (const token of tokens) {
    if (token.kind === 'option') {
      if (seen.has(token.name)) {
        throw new UsageError(`Option '--${token.name}' given more than once`, { showUsage: true });
      }
      seen.add(token.name);
    }
  }
}
