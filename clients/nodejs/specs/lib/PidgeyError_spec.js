import { PidgeyError } from '../../lib/PidgeyError.js';

describe('PidgeyError', () => {
  it('is an Error and a PidgeyError', () => {
    const error = new PidgeyError('boom');

    expect(error instanceof Error).toBe(true);
    expect(error instanceof PidgeyError).toBe(true);
  });

  it('exposes name and message', () => {
    const error = new PidgeyError('boom');

    expect(error.name).toBe('PidgeyError');
    expect(error.message).toBe('boom');
  });

  it('exposes the given code', () => {
    const error = new PidgeyError('boom', { code: 'NO_SMTP_CONNECTION' });

    expect(error.code).toBe('NO_SMTP_CONNECTION');
  });

  it('has no code by default', () => {
    expect(new PidgeyError('boom').code).toBeUndefined();
  });
});
