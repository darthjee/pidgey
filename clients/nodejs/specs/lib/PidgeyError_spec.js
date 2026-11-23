import { inspect } from 'node:util';
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
    expect(new PidgeyError('boom', { code: 'NO_SMTP_CONNECTION' }).code).toBe('NO_SMTP_CONNECTION');
  });

  it('has no code by default', () => {
    expect(new PidgeyError('boom').code).toBeUndefined();
  });

  it('is not possibly sent by default', () => {
    expect(new PidgeyError('boom').possiblySent).toBe(false);
  });

  it('exposes possiblySent', () => {
    expect(new PidgeyError('boom', { possiblySent: true }).possiblySent).toBe(true);
  });

  describe('optional fields', () => {
    it('are absent when not given', () => {
      const error = new PidgeyError('boom', { code: 'ABORTED' });

      for (const field of ['statusCode', 'details', 'smtp', 'body', 'bodyTruncated']) {
        expect(Object.hasOwn(error, field)).toBe(false);
      }
    });

    it('are set and enumerable when given', () => {
      const smtp = { code: 550, message: 'no' };
      const error = new PidgeyError('boom', { code: 'X', statusCode: 502, details: ['a'], smtp });

      expect(error.statusCode).toBe(502);
      expect(error.details).toEqual(['a']);
      expect(error.smtp).toBe(smtp);
      expect(Object.keys(error)).toEqual(['name', 'code', 'possiblySent', 'statusCode', 'details', 'smtp']);
    });
  });

  describe('body and bodyTruncated', () => {
    const error = new PidgeyError('boom', { code: 'X', body: 'raw text', bodyTruncated: true });

    it('are readable', () => {
      expect(error.body).toBe('raw text');
      expect(error.bodyTruncated).toBe(true);
    });

    it('default bodyTruncated to false', () => {
      expect(new PidgeyError('boom', { body: 'x' }).bodyTruncated).toBe(false);
    });

    it('are not enumerable', () => {
      expect(Object.keys(error)).not.toContain('body');
      expect(Object.keys(error)).not.toContain('bodyTruncated');
      expect(Object.getOwnPropertyNames(error)).toContain('body');
    });

    it('are read-only', () => {
      const descriptor = Object.getOwnPropertyDescriptor(error, 'body');

      expect(descriptor.writable).toBe(false);
      expect(descriptor.enumerable).toBe(false);
    });

    it('stay out of JSON.stringify and util.inspect', () => {
      expect(JSON.stringify(error)).not.toContain('raw text');
      expect(inspect(error, { depth: 0 })).not.toContain('raw text');
    });
  });
});
