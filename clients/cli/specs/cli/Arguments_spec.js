import { parseArguments, UsageError, USAGE } from '../../cli/Arguments.js';

describe('parseArguments', () => {
  it('accepts --version', () => {
    expect(parseArguments(['--version'])).toEqual({ help: false, version: true });
  });

  it('accepts --help', () => {
    expect(parseArguments(['--help'])).toEqual({ help: true, version: false });
  });

  it('accepts both flags', () => {
    expect(parseArguments(['--help', '--version'])).toEqual({ help: true, version: true });
  });

  it('rejects an unknown flag', () => {
    expect(() => parseArguments(['--bogus'])).toThrowMatching(
      (error) => error instanceof UsageError && error.message.includes('--bogus'),
    );
  });

  it('rejects --token as an unknown flag', () => {
    expect(() => parseArguments(['--token', 'secret'])).toThrowMatching(
      (error) => error instanceof UsageError && !error.message.includes('secret'),
    );
  });

  it('rejects a positional argument', () => {
    expect(() => parseArguments(['send'])).toThrowError(UsageError);
  });

  it('rejects a repeated flag', () => {
    expect(() => parseArguments(['--version', '--version'])).toThrowMatching(
      (error) => error instanceof UsageError && error.message.includes('--version'),
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
});

describe('USAGE', () => {
  it('lists --help and --version', () => {
    expect(USAGE).toContain('--help');
    expect(USAGE).toContain('--version');
  });
});
