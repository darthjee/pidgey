import { parseArgs } from 'node:util';

/**
 * The usage text of the `pidgey` command.
 * @type {string}
 */
export const USAGE = `Usage: pidgey [--help | --version]

Options:
  --help     Print this usage and exit.
  --version  Print the CLI and library versions and exit.
`;

/**
 * Raised when the command line is not valid. The CLI answers it with the usage and exit 2.
 */
export class UsageError extends Error {
  /**
   * Builds a UsageError.
   * @param {string} message - One-line description of the mistake.
   */
  constructor(message) {
    super(message);
    this.name = 'UsageError';
  }
}

const OPTIONS = /** @type {const} */ ({
  help: { type: 'boolean' },
  version: { type: 'boolean' },
});

/**
 * Parses the command line arguments (without the node binary and the script path).
 * @param {string[]} argv - The arguments.
 * @returns {{ help: boolean, version: boolean }} Which flags were given.
 * @throws {UsageError} On an unknown flag, a positional argument, a repeated flag or no flag.
 */
export function parseArguments(argv) {
  let parsed;
  try {
    parsed = parseArgs({
      args: argv,
      options: OPTIONS,
      strict: true,
      allowPositionals: false,
      tokens: true,
    });
  } catch (error) {
    throw new UsageError(/** @type {Error} */ (error).message.split('\n')[0]);
  }

  const seen = new Set();
  for (const token of parsed.tokens) {
    if (token.kind === 'option') {
      if (seen.has(token.name)) {
        throw new UsageError(`Option '--${token.name}' given more than once`);
      }
      seen.add(token.name);
    }
  }

  if (seen.size === 0) {
    throw new UsageError('Missing option: expected --help or --version');
  }

  return { help: seen.has('help'), version: seen.has('version') };
}
