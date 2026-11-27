import { parseArguments, UsageError, USAGE } from '../../cli/Arguments.js';

const REQUIRED = ['--to', 'a@example.com', '--subject', 'Hi'];

describe('parseArguments', () => {
  it('parses every flag', () => {
    const args = parseArguments([
      '--to', 'a@example.com',
      '--subject', 'Hi',
      '--text', 'plain',
      '--html', '<p>rich</p>',
      '--token-file', '/run/token',
      '--url', 'https://pidgey.example.com',
      '--idempotency-key', 'key-1',
      '--retry-ambiguous',
      '--allow-insecure-http',
      '--verbose',
    ]);

    expect(args).toEqual({
      mode: 'send',
      to: 'a@example.com',
      subject: 'Hi',
      text: 'plain',
      html: '<p>rich</p>',
      tokenFile: '/run/token',
      url: 'https://pidgey.example.com',
      idempotencyKey: 'key-1',
      retryAmbiguous: true,
      allowInsecureHttp: true,
      verbose: true,
    });
  });

  it('defaults the optional flags', () => {
    expect(parseArguments(REQUIRED)).toEqual({
      mode: 'send',
      to: 'a@example.com',
      subject: 'Hi',
      text: undefined,
      html: undefined,
      tokenFile: undefined,
      url: undefined,
      idempotencyKey: undefined,
      retryAmbiguous: false,
      allowInsecureHttp: false,
      verbose: false,
    });
  });

  it('keeps an empty --text as an empty string', () => {
    expect(parseArguments([...REQUIRED, '--text', '']).text).toBe('');
  });

  it('passes --idempotency-key through as given', () => {
    expect(parseArguments([...REQUIRED, '--idempotency-key', ' odd key ']).idempotencyKey).toBe(' odd key ');
  });

  it('accepts --version', () => {
    expect(parseArguments(['--version'])).toEqual({ mode: 'version' });
  });

  it('accepts --help', () => {
    expect(parseArguments(['--help'])).toEqual({ mode: 'help' });
  });

  it('prefers --help over --version', () => {
    expect(parseArguments(['--version', '--help'])).toEqual({ mode: 'help' });
  });

  it('prefers --help over the required flags being missing', () => {
    expect(parseArguments(['--help', '--text', 'x'])).toEqual({ mode: 'help' });
  });

  it('rejects an unknown flag with the usage', () => {
    expect(() => parseArguments(['--bogus'])).toThrowMatching(
      (error) => error instanceof UsageError && error.showUsage && error.message.includes('--bogus'),
    );
  });

  it('rejects --token as an unknown flag without echoing its value', () => {
    expect(() => parseArguments([...REQUIRED, '--token', 'secret'])).toThrowMatching(
      (error) => error instanceof UsageError && error.showUsage && !error.message.includes('secret'),
    );
  });

  it('rejects a positional argument', () => {
    expect(() => parseArguments([...REQUIRED, 'send'])).toThrowMatching(
      (error) => error instanceof UsageError && error.showUsage,
    );
  });

  it('rejects a repeated flag', () => {
    expect(() => parseArguments(['--version', '--version'])).toThrowMatching(
      (error) => error instanceof UsageError && error.showUsage && error.message.includes('--version'),
    );
  });

  it('rejects a repeated --to', () => {
    expect(() => parseArguments(['--to', 'a@example.com', '--to', 'b@example.com', '--subject', 'Hi']))
      .toThrowMatching((error) => error instanceof UsageError && error.message.includes('--to'));
  });

  it('rejects a flag missing its value', () => {
    expect(() => parseArguments(['--to'])).toThrowError(UsageError);
  });

  it('requires --to', () => {
    expect(() => parseArguments(['--subject', 'Hi'])).toThrowMatching(
      (error) => error instanceof UsageError && error.showUsage && error.message.includes('--to'),
    );
  });

  it('requires --subject', () => {
    expect(() => parseArguments(['--to', 'a@example.com'])).toThrowMatching(
      (error) => error instanceof UsageError && error.showUsage && error.message.includes('--subject'),
    );
  });

  it('rejects no argument', () => {
    expect(() => parseArguments([])).toThrowError(UsageError);
  });

  it('gives a one-line message', () => {
    expect(() => parseArguments(['--bogus'])).toThrowMatching((error) => !error.message.includes('\n'));
  });
});

describe('UsageError', () => {
  it('is named UsageError', () => {
    expect(new UsageError('x').name).toBe('UsageError');
  });

  it('does not ask for the usage by default', () => {
    expect(new UsageError('x').showUsage).toBeFalse();
  });

  it('asks for the usage when told to', () => {
    expect(new UsageError('x', { showUsage: true }).showUsage).toBeTrue();
  });
});

describe('USAGE', () => {
  it('lists every flag', () => {
    for (const flag of [
      '--to', '--subject', '--text', '--html', '--token-file', '--url', '--idempotency-key',
      '--retry-ambiguous', '--allow-insecure-http', '--verbose', '--help', '--version',
    ]) {
      expect(USAGE).toContain(flag);
    }
  });

  it('names the token sources and the exit codes', () => {
    expect(USAGE).toContain('PIDGEY_TOKEN');
    expect(USAGE).toContain('PIDGEY_URL');
    expect(USAGE).toContain('130');
  });

  it('does not offer a --token flag', () => {
    expect(USAGE).not.toMatch(/--token\b(?!-file)/);
  });
});
