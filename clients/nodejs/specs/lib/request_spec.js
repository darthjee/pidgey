import {
  buildBody, failArgument, isUnambiguousNetworkError, isValidIdempotencyKey, MESSAGE_FIELDS, tooLargeError,
} from '../../lib/request.js';
import { PidgeyError } from '../../lib/PidgeyError.js';

const catchError = (fn) => {
  try {
    fn();
  } catch (error) {
    return error;
  }
  return undefined;
};

describe('request', () => {
  describe('failArgument', () => {
    it('throws a CLIENT_CONFIGURATION error that was not sent', () => {
      const error = catchError(() => failArgument('nope'));

      expect(error).toBeInstanceOf(PidgeyError);
      expect(error.code).toBe('CLIENT_CONFIGURATION');
      expect(error.possiblySent).toBe(false);
      expect(error.message).toBe('nope');
    });
  });

  describe('isValidIdempotencyKey', () => {
    it('accepts 1 to 255 printable ASCII characters', () => {
      for (const key of ['a', 'order-1234', '!~', 'x'.repeat(255), 'a/b:c=d']) {
        expect(isValidIdempotencyKey(key)).toBe(true);
      }
    });

    it('rejects everything else', () => {
      for (const key of ['', 'x'.repeat(256), 'a b', ' ', 'café', 'a\nb', 'a\u007f', 42, null, undefined, {}]) {
        expect(isValidIdempotencyKey(key)).toBe(false);
      }
    });
  });

  describe('buildBody', () => {
    it('keeps only the whitelisted fields', () => {
      const body = buildBody({ to: 'a@b.c', subject: 's', text: 't', html: '<p>h</p>', from: 'x@y.z', extra: 1 });

      expect(JSON.parse(body)).toEqual({ to: 'a@b.c', subject: 's', text: 't', html: '<p>h</p>' });
    });

    it('omits undefined values but keeps null and empty ones for the API to judge', () => {
      const body = buildBody({ to: 'a@b.c', subject: undefined, text: null, html: '' });

      expect(JSON.parse(body)).toEqual({ to: 'a@b.c', text: null, html: '' });
    });

    it('does not validate the values', () => {
      expect(JSON.parse(buildBody({ to: 12, subject: ['x'] }))).toEqual({ to: 12, subject: ['x'] });
    });

    it('sends an empty object for an empty message', () => {
      expect(buildBody({})).toBe('{}');
    });

    it('lists the v1 fields', () => {
      expect(MESSAGE_FIELDS).toEqual(['to', 'subject', 'text', 'html']);
    });

    for (const message of [undefined, null, [], [{ to: 'a' }], 'text', 42, true]) {
      it(`rejects ${JSON.stringify(message)} as the message`, () => {
        const error = catchError(() => buildBody(message));

        expect(error).toBeInstanceOf(PidgeyError);
        expect(error.code).toBe('CLIENT_CONFIGURATION');
        expect(error.possiblySent).toBe(false);
      });
    }

    it('rejects a message that cannot be serialized', () => {
      const error = catchError(() => buildBody({ to: 10n }));

      expect(error.code).toBe('CLIENT_CONFIGURATION');
      expect(error.message).toMatch(/JSON/);
    });
  });

  describe('isUnambiguousNetworkError', () => {
    const withCause = (code) => new TypeError('fetch failed', { cause: Object.assign(new Error('x'), { code }) });

    for (const code of [
      'ECONNREFUSED', 'ENOTFOUND', 'EAI_AGAIN', 'EHOSTUNREACH', 'ENETUNREACH',
      'ERR_TLS_CERT_ALTNAME_INVALID', 'ERR_SSL_WRONG_VERSION_NUMBER', 'CERT_HAS_EXPIRED', 'CERT_UNTRUSTED',
      'DEPTH_ZERO_SELF_SIGNED_CERT', 'SELF_SIGNED_CERT_IN_CHAIN', 'UNABLE_TO_VERIFY_LEAF_SIGNATURE',
      'UNABLE_TO_GET_ISSUER_CERT_LOCALLY',
    ]) {
      it(`${code} is unambiguous`, () => {
        expect(isUnambiguousNetworkError(withCause(code))).toBe(true);
      });
    }

    for (const code of ['ECONNRESET', 'UND_ERR_SOCKET', 'ETIMEDOUT', 'EPIPE', 'UND_ERR_CONNECT_TIMEOUT', 'ERR_OTHER']) {
      it(`${code} is ambiguous`, () => {
        expect(isUnambiguousNetworkError(withCause(code))).toBe(false);
      });
    }

    it('reads the code of the error itself', () => {
      expect(isUnambiguousNetworkError(Object.assign(new Error('x'), { code: 'ENOTFOUND' }))).toBe(true);
    });

    it('walks nested causes', () => {
      const inner = Object.assign(new Error('inner'), { code: 'ECONNREFUSED' });
      const middle = new Error('middle', { cause: inner });
      const outer = new TypeError('fetch failed', { cause: middle });

      expect(isUnambiguousNetworkError(outer)).toBe(true);
    });

    it('decides on the first code found, outermost first', () => {
      const inner = Object.assign(new Error('inner'), { code: 'ECONNREFUSED' });
      const outer = new TypeError('fetch failed', { cause: Object.assign(new Error('o'), { code: 'ECONNRESET', cause: inner }) });

      expect(isUnambiguousNetworkError(outer)).toBe(false);
    });

    it('ignores a non-string code', () => {
      const error = new Error('x', { cause: Object.assign(new Error('y'), { code: 20 }) });

      expect(isUnambiguousNetworkError(error)).toBe(false);
    });

    it('is ambiguous without any code', () => {
      expect(isUnambiguousNetworkError(new TypeError('fetch failed'))).toBe(false);
      expect(isUnambiguousNetworkError(new TypeError('fetch failed', { cause: 'text' }))).toBe(false);
    });

    it('is ambiguous for a thrown non-object', () => {
      expect(isUnambiguousNetworkError('boom')).toBe(false);
      expect(isUnambiguousNetworkError(null)).toBe(false);
    });

    it('stops on a cyclic cause chain', () => {
      const a = new Error('a');
      const b = new Error('b', { cause: a });
      a.cause = b;

      expect(isUnambiguousNetworkError(a)).toBe(false);
    });
  });

  describe('tooLargeError', () => {
    it('is possibly sent on a 2xx', () => {
      const error = tooLargeError(200);

      expect(error.code).toBe('INVALID_RESPONSE');
      expect(error.statusCode).toBe(200);
      expect(error.possiblySent).toBe(true);
    });

    it('is not possibly sent on another status', () => {
      expect(tooLargeError(502).possiblySent).toBe(false);
      expect(tooLargeError(302).possiblySent).toBe(false);
    });
  });
});
